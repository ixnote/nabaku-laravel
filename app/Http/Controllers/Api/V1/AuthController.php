<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Bank;
use App\Models\Cac;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use App\Models\Wallet;
use App\Services\JwtService;
use App\Services\NotificationService;
use App\Support\Access;
use App\Support\Api;
use App\Support\Atomic;
use App\Support\Ids;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private NotificationService $mail) {}

    public function register(Request $r)
    {
        $v = $r->validate(['email' => 'required|email|max:254', 'phone' => 'required|string|max:30', 'password' => 'required|string|min:6|max:50']);
        $v['email'] = strtolower($v['email']);
        abort_if(Organization::where('email', $v['email'])->exists() || User::where('email', $v['email'])->exists(), 400, 'Email is already in use');
        abort_if(Organization::where('phone', $v['phone'])->exists(), 400, 'Phone is already in use');
        $token = Str::random(64);
        $org = Atomic::run(function () use ($v, $token) {
            $org = Organization::create([...$v, 'password' => Hash::make($v['password']), 'activationToken' => hash('sha256', $token), 'activationExpires' => now()->addDay()]);
            Wallet::create(['organization' => $org->getKey()]);
            $this->mail->activation($org, $token);

            return $org;
        });

        return Api::ok($org, 'Please check your mail to verify your account.', 201);
    }

    private function account(string $id)
    {
        $id = strtolower($id);

        return Organization::where('email', $id)->orWhere('phone', $id)->first() ?? User::where('email', $id)->first();
    }

    public function login(Request $r, JwtService $jwt)
    {
        $v = $r->validate(['id' => 'required|string|max:254', 'password' => 'required|string|max:200']);
        $user = $this->account($v['id']);
        abort_unless($user, 404, 'Not Found');
        abort_unless(password_verify($v['password'], (string) $user->password), 400, 'Invalid credentials');
        abort_if($user->blocked || $user->deletedAt, 403, 'Your account has been restricted, please contact support');
        abort_unless($user->isActive, 403, 'Activate your account');
        $org = $user instanceof User ? Organization::find((string) $user->organization) : $user;
        abort_unless($org && $org->isActive && ! $org->blocked && ! $org->deletedAt, 403, 'Organization not available');
        Wallet::firstOrCreate(['organization' => Ids::object((string) $org->getKey())]);

        return response()->json(['success' => true, 'message' => 'login successful', 'data' => $user, 'authorization' => ['token' => $jwt->issue($user), 'expiresIn' => config('fundraiser.jwt_ttl')]]);
    }

    private function tokenAccount(string $token, string $field, bool $manager = false)
    {
        abort_unless(strlen($token) >= 20 && strlen($token) <= 128, 403, 'Invalid or expired token');
        foreach ($manager ? [User::class] : [Organization::class, User::class] as $model) {
            $account = $model::where($field, hash('sha256', $token))->first();
            if (! $account) {
                $account = $model::where($field, $token)->first();
            }
            if ($account) {
                return $account;
            }
        }
        abort(403, 'Invalid or expired token');
    }

    public function activate(Request $r, string $token)
    {
        return $this->activateToken($r, $token, false);
    }

    public function activateManager(Request $r, string $token)
    {
        return $this->activateToken($r, $token, true);
    }

    private function activateToken(Request $r, string $token, bool $manager)
    {
        $v = $manager ? $r->validate(['password' => 'required|string|min:6|max:50']) : [];
        $account = Atomic::run(function () use ($token, $manager, $v) {
            $account = $this->tokenAccount($token, 'activationToken', $manager);
            if ($account instanceof User) {
                abort_unless($manager, 400, 'Use the manager activation form to set a password');
            }
            $expiry = $account->activationExpires ?? (config('fundraiser.legacy_activation_until') ? Carbon::parse(config('fundraiser.legacy_activation_until')) : null);
            abort_unless($expiry && $expiry->isFuture(), 403, 'Invalid or expired token');
            $account->activationToken = null;
            $account->activationExpires = null;
            $account->isActive = true;
            if ($manager) {
                $account->password = Hash::make($v['password']);
            }
            $account->save();

            return $account;
        });

        return Api::ok($account, 'Account activation successful');
    }

    public function activateAdmin(string $id)
    {
        $org = Access::find(Organization::class, $id);
        $org->update(['activationToken' => null, 'activationExpires' => null, 'isActive' => true]);

        return Api::ok($org, 'Account activation successful');
    }

    public function resend(Request $r)
    {
        $v = $r->validate(['id' => 'required|string|max:254']);
        Atomic::run(function () use ($v) {
            $user = $this->account($v['id']);
            if (! $user || $user->isActive || $user->blocked) {
                return;
            }
            $token = Str::random(64);
            $user->update(['activationToken' => hash('sha256', $token), 'activationExpires' => now()->addDay()]);
            $this->mail->activation($user, $token);
        });

        return Api::ok(null, 'If the account requires activation, an email has been sent.');
    }

    public function forgot(Request $r)
    {
        $v = $r->validate(['id' => 'required|string|max:254']);
        Atomic::run(function () use ($v) {
            $user = $this->account($v['id']);
            if (! $user || $user->blocked) {
                return;
            }
            $token = Str::random(64);
            $user->update(['resetPasswordToken' => hash('sha256', $token), 'resetPasswordExpires' => now()->addMinutes(15)]);
            $url = NotificationService::frontendLink(config('fundraiser.reset_path'), ['token' => $token, 'email' => $user->email]);
            $this->mail->enqueue($user->email, 'Password Reset Mail', 'Use this link to reset your password. It expires in 15 minutes.', $url);
        });

        return Api::ok(null, 'If the account exists, a password reset link has been sent. The link expires in 15 minutes.');
    }

    public function reset(Request $r)
    {
        $v = $r->validate(['token' => 'required|string|max:128', 'password' => 'required|string|min:6|max:50']);
        Atomic::run(function () use ($v) {
            $user = $this->tokenAccount($v['token'], 'resetPasswordToken');
            abort_unless($user->resetPasswordExpires && $user->resetPasswordExpires->isFuture(), 403, 'Invalid or expired token');
            $user->update(['password' => Hash::make($v['password']), 'resetPasswordToken' => null, 'resetPasswordExpires' => null]);
        });

        return Api::ok(null, 'Password changed successfully, please login to proceed to your dashboard');
    }

    public function profile(Request $r)
    {
        $org = Organization::findOrFail(Access::organization($r));
        $data = $org->toArray();
        foreach (['bank' => Bank::class, 'address' => Address::class, 'cac' => Cac::class, 'contact' => Contact::class] as $field => $model) {
            $data[$field] = $model::where('organization', Ids::object((string) $org->getKey()))->first();
        }

        return Api::ok($data, 'Organization fetched successfully');
    }

    public function setProfile(Request $r)
    {
        Access::owner($r);
        $v = $r->validate([
            // Optional so settings edits don't touch KYB progress
            'profile_step' => 'sometimes|integer|min:1|max:10',
            'organization_details' => 'sometimes|array:name,description,type,profile_image,cover_image',
            'organization_details.name' => 'sometimes|string|min:3|max:200',
            'organization_details.description' => 'nullable|string|max:20000', 'organization_details.type' => 'sometimes|in:faith-based,non-profit,others',
            // An empty value clears an image from the settings page
            'organization_details.profile_image' => 'nullable|url|max:2048', 'organization_details.cover_image' => 'nullable|url|max:2048',
            'address' => 'sometimes|array:street,city,state,zipCode', 'address.street' => 'required_with:address|string|max:300', 'address.city' => 'required_with:address|string|max:100', 'address.state' => 'required_with:address|string|max:100', 'address.zipCode' => 'nullable|regex:/^[0-9]{6}$/',
            'cac_details' => 'sometimes|array:name,registration_certificate,registration_number', 'cac_details.name' => 'required_with:cac_details|string|min:3|max:200', 'cac_details.registration_certificate' => 'required_with:cac_details|url|max:2048', 'cac_details.registration_number' => 'required_with:cac_details|string|max:100',
            'bank_details' => 'sometimes|array:bankCode,bankName,accountNumber,accountName', 'bank_details.bankCode' => 'sometimes|string|max:20', 'bank_details.bankName' => 'required_with:bank_details|string|max:100', 'bank_details.accountNumber' => 'required_with:bank_details|string|regex:/^[0-9]{10}$/', 'bank_details.accountName' => 'required_with:bank_details|string|max:200',
            'contact_details' => 'sometimes|array:phone,youtube,facebook,email,instagram,twitter', 'contact_details.*' => 'nullable|string|max:500', 'contact_details.email' => 'nullable|email|max:254',
        ]);
        Atomic::run(function () use ($r, $v) {
            $org = Organization::findOrFail(Access::organization($r));
            $org->fill(array_map(fn ($value) => $value ?? '', $v['organization_details'] ?? []));
            if (isset($v['profile_step'])) {
                $org->profile_step = $v['profile_step'];
            }
            foreach (['address' => [Address::class, 'address'], 'cac_details' => [Cac::class, 'cac'], 'bank_details' => [Bank::class, 'bank'], 'contact_details' => [Contact::class, 'contact']] as $input => [$model,$field]) {
                if (! isset($v[$input])) {
                    continue;
                }
                $values = $v[$input];
                if ($field === 'bank') {
                    $values['isBankVerified'] = false;
                }
                $doc = $model::updateOrCreate(['organization' => Ids::object((string) $org->getKey())], $values);
                $org->$field = $doc->getKey();
                if ($field === 'cac') {
                    $org->name = $values['name'];
                }
            }
            $org->save();
        });

        return Api::ok(null, 'Organization updated successfully');
    }

    public function states()
    {
        return Api::ok(json_decode(file_get_contents(resource_path('data/states.json')), true), 'states fetched');
    }
}

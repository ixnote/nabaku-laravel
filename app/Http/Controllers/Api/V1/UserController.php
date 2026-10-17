<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\Access;
use App\Support\Api;
use App\Support\Atomic;
use App\Support\Ids;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function add(Request $r, NotificationService $mail)
    {
        Access::owner($r);
        $v = $r->validate(['email' => 'required|email|max:254']);
        $email = strtolower($v['email']);
        Atomic::run(function () use ($r, $email, $mail) {
            // Serialize manager invitations on the parent to enforce the five-user limit under concurrency.
            $org = Organization::findOrFail(Access::organization($r));
            $org->managerRevision = (int) $org->managerRevision + 1;
            $org->save();
            abort_if(User::where('organization', Ids::object((string) $org->getKey()))->count() >= 5, 400, 'Maximum number of users reached');
            abort_if(User::where('email', $email)->exists() || Organization::where('email', $email)->exists(), 400, 'Email already exists');
            $token = Str::random(64);
            $user = User::create(['email' => $email, 'organization' => $org->getKey(), 'activationToken' => hash('sha256', $token), 'activationExpires' => now()->addDay()]);
            $mail->activation($user, $token);
        });

        return Api::ok(null, 'Mail sent successfully. Awaiting confirmation.', 201);
    }

    public function delete(Request $r, ?string $id = null)
    {
        Access::owner($r);
        $id ??= $r->validate(['userId' => 'required|string'])['userId'];
        $user = Access::find(User::class, $id);
        Access::owns($r, $user->organization);
        $user->delete();

        return Api::ok(null, 'User deleted successfully.');
    }
}

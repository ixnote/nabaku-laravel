<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\MongoTestCase;

class EndpointCoverageTest extends MongoTestCase
{
    public function test_remaining_read_routes_and_administrative_actions(): void
    {
        $o = $this->organization();
        $admin = $this->organization(['isAdmin' => true]);
        $oh = $this->token($o);
        $ah = $this->token($admin);
        $p = $this->project($o);
        $t = $this->transaction($o, $p, ['status' => 'successful']);
        $b = $this->bank($o);
        $a = Activity::create(['organization' => $o->getKey(), 'name' => 'Meeting', 'start' => '2026-10-01', 'end' => '2026-10-02']);
        $w = WithdrawalRequest::create(['organization' => $o->getKey(), 'amount' => 1000, 'reference' => 'read-test']);
        foreach (['/api/v1/organization', '/api/v1/project/all', '/api/v1/activity/all', '/api/v1/activity/'.$a->getKey(), '/api/v1/donation', '/api/v1/donation/'.$t->getKey(), '/api/v1/wallet', '/api/v1/wallet/'.$o->getKey(), '/api/v1/withdraw/all', '/api/v1/withdrawal/all', '/api/v1/withdraw/single/'.$w->getKey()] as $url) {
            $this->getJson($url, $oh)->assertOk();
        }
        foreach (['/api/v1/organization/list', '/api/v1/organization/'.$o->getKey(), '/api/v1/project/random', '/api/v1/project/organization/'.$o->getKey(), '/api/v1/activity/public/'.$o->getKey()] as $url) {
            $this->getJson($url)->assertOk();
        }
        foreach (['/api/v1/project/admin/all', '/api/v1/withdraw/admin/all', '/api/v1/withdraw/admin/requests', '/api/v1/withdrawal/admin/all', '/api/v1/withdrawal/admin/requests'] as $url) {
            $this->getJson($url, $ah)->assertOk();
        }
        $this->putJson('/api/v1/organization/update', ['name' => 'New Name'], $oh)->assertOk();
        $this->putJson('/api/v1/organization/update/password', ['password' => 'updatedpassword'], $oh)->assertOk();
        $this->putJson('/api/v1/organization/update/organization-password', ['user' => (string) $o->getKey(), 'password' => 'adminchangedpassword'], $ah)->assertOk();
        $this->postJson('/api/v1/authentication/resend-activation', ['id' => $o->email])->assertOk();
        $this->getJson('/api/v1/authentication/activate/user/'.$o->getKey(), $ah)->assertOk();
        $this->postJson('/api/v1/organization/block/'.$o->getKey(), [], $ah)->assertOk()->assertJsonPath('data.blocked', true);
        $this->postJson('/api/v1/organization/block/'.$o->getKey(), [], $ah)->assertOk()->assertJsonPath('data.blocked', false);
        $m = User::create(['email' => 'delete@example.test', 'organization' => $o->getKey()]);
        $this->getJson('/api/v1/user/delete?userId='.$m->getKey(), $oh)->assertOk();
    }

    public function test_provider_and_upload_endpoints(): void
    {
        $o = $this->organization();
        $admin = $this->organization(['isAdmin' => true]);
        $b = $this->bank($o);
        $h = $this->token($o);
        config(['services.flutterwave.secret' => 'test', 'services.monnify.key' => 'key', 'services.monnify.secret' => 'secret', 'services.monnify.wallet' => '123', 'services.cloudinary.cloud' => 'test', 'services.cloudinary.key' => 'key', 'services.cloudinary.secret' => 'secret']);
        Http::fake(['*accounts/resolve' => Http::response(['status' => 'success', 'message' => 'Resolved', 'data' => ['account_name' => 'Verified']]), '*auth/login' => Http::response(['responseBody' => ['accessToken' => 'access']]), '*wallet-balance*' => Http::response(['requestSuccessful' => true, 'responseBody' => ['availableBalance' => 5000]]), '*cloudinary.com*' => Http::response(['secure_url' => 'https://images.example.test/image.jpg', 'resource_type' => 'image'])]);
        $this->postJson('/api/v1/bank/verify', ['account_number' => '0123456789', 'account_bank' => '044'], $h)->assertOk();
        $this->putJson('/api/v1/bank/update/bank-details', ['bankCode' => '044', 'bankName' => 'Test', 'accountName' => 'Test', 'accountNumber' => '0123456789'], $h)->assertOk();
        $this->getJson('/api/v1/wallet/balance/admin', $this->token($admin))->assertOk()->assertJsonPath('data.availableBalance', 5000);
        $this->getJson('/api/v1/wallet', $this->token($admin))->assertOk()->assertJsonPath('data.monnifyBalance', 5000);
        $this->getJson('/api/v1/file/signature')->assertUnauthorized();
        $signed = $this->getJson('/api/v1/file/signature', $h)->assertOk()->assertJsonPath('data.cloudName', 'test')->assertJsonPath('data.apiKey', 'key')->assertJsonPath('data.folder', 'nabaku/'.$o->getKey())->json('data');
        $this->assertSame(sha1('folder=nabaku/'.$o->getKey().'&timestamp='.$signed['timestamp'].'secret'), $signed['signature']);
        $this->call('PUT', '/api/v1/file/upload', [], [], ['file' => UploadedFile::fake()->image('test.jpg')], $this->transformHeadersToServerVars($h))->assertOk();
        $this->call('PUT', '/api/v1/organization/update/avatar', [], [], ['file' => UploadedFile::fake()->image('avatar.jpg')], $this->transformHeadersToServerVars($h))->assertOk();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Bank;
use App\Models\Outbox;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\MongoTestCase;

class ResourceTest extends MongoTestCase
{
    public function test_project_activity_upload_bank_and_public_queries(): void
    {
        $org = $this->organization();
        $other = $this->organization();
        $headers = $this->token($org);
        config(['services.cloudinary.cloud' => 'test', 'services.cloudinary.key' => 'key', 'services.cloudinary.secret' => 'secret', 'services.flutterwave.secret' => 'secret']);
        Http::fake(['*cloudinary.com*' => Http::response(['secure_url' => 'https://images.example.test/cover.jpg', 'resource_type' => 'image']), '*accounts/resolve' => Http::response(['status' => 'success', 'data' => ['account_name' => 'Verified Name']]), '*banks/NG' => Http::response(['status' => 'success', 'data' => [['code' => '044', 'name' => 'Test Bank']]])]);
        $create = $this->post('/api/v1/project/create', ['title' => 'School', 'description' => 'Classroom', 'target_amount' => 10000, 'cover_image' => UploadedFile::fake()->image('cover.jpg')], $headers)->assertOk();
        $id = $create->json('data._id');
        $this->getJson('/api/v1/project/'.$id)->assertOk()->assertJsonPath('data.cover_image', 'https://images.example.test/cover.jpg');
        $this->putJson('/api/v1/project/update/'.$id, ['title' => 'Updated'], $this->token($other))->assertForbidden();
        $this->putJson('/api/v1/project/update/'.$id, ['title' => 'Updated', 'organization' => (string) $other->getKey(), 'donated_amount' => 9000], $headers)->assertOk()->assertJsonPath('data.organization', (string) $org->getKey())->assertJsonPath('data.donated_amount', 0);
        $this->postJson('/api/v1/activity/add', ['name' => 'Meeting', 'start' => '2026-10-01', 'end' => '2026-10-02'], $headers)->assertCreated();
        $a = Activity::firstOrFail();
        $this->putJson('/api/v1/activity/'.$a->getKey(), ['name' => 'New Meeting'], $headers)->assertOk();
        $this->getJson('/api/v1/activity/public')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/bank/list')->assertOk();
        $payload = ['bankCode' => '044', 'bankName' => 'Test Bank', 'accountName' => 'Client Name', 'accountNumber' => '0123456789'];
        $this->postJson('/api/v1/bank/create', $payload, $headers)->assertOk();
        $b = Bank::firstOrFail();
        $this->assertSame('Verified Name', $b->accountName);
        $this->putJson('/api/v1/bank/update/'.$b->getKey(), $payload, $headers)->assertOk();
        $this->getJson('/api/v1/bank', $headers)->assertOk()->assertJsonPath('data.accountNumber', '0123456789');
        $this->getJson('/api/v1/global/search?name=Updated')->assertOk()->assertJsonCount(1, 'projects.data');
        $this->getJson('/api/v1/project/all?page=0', $headers)->assertStatus(400);
        $this->getJson('/api/v1/project/invalid')->assertNotFound();
        $this->deleteJson('/api/v1/project/delete/'.$id, [], $headers)->assertOk();
        $this->getJson('/api/v1/project/'.$id)->assertNotFound();
        $this->deleteJson('/api/v1/activity/delete/'.$a->getKey(), [], $headers)->assertOk();
    }

    public function test_manager_invitation_activation_deletion_and_limit(): void
    {
        $org = $this->organization();
        $headers = $this->token($org);
        $this->postJson('/api/v1/user/add', ['email' => 'manager@example.test'], $headers)->assertCreated();
        $m = User::firstOrFail();
        $payload = json_decode(Crypt::decryptString(Outbox::first()->encrypted_payload), true);
        $token = basename($payload['url']);
        $this->putJson('/api/v1/authentication/activate/manager/'.$token, ['password' => 'password123'])->assertOk();
        $this->postJson('/api/v1/authentication/login', ['id' => $m->email, 'password' => 'password123'])->assertOk();
        $this->deleteJson('/api/v1/user/'.$m->getKey(), [], $headers)->assertOk();
        $this->assertSame(0, User::count());
        for ($i = 0; $i < 5; $i++) {
            User::create(['email' => "manager$i@example.test", 'organization' => $org->getKey()]);
        }
        $this->postJson('/api/v1/user/add', ['email' => 'sixth@example.test'], $headers)->assertStatus(400);
    }

    public function test_reports_public_redaction_and_soft_delete(): void
    {
        $org = $this->organization();
        $admin = $this->organization(['isAdmin' => true]);
        $p = $this->project($org);
        $this->transaction($org, $p, ['status' => 'successful', 'createdAt' => now(), 'amount' => 1000]);
        $headers = $this->token($org);
        $this->getJson('/api/v1/organization/dashboard', $headers)->assertOk()->assertJsonPath('data.fundsRaised.number', 1000);
        $this->getJson('/api/v1/organization/overview', $headers)->assertOk()->assertJsonPath('data.income.amount', 1000);
        $this->getJson('/api/v1/donation/graph/year?startDate='.now()->startOfYear()->toDateString().'&endDate='.now()->endOfYear()->toDateString(), $headers)->assertOk()->assertJsonCount(12, 'results');
        $this->getJson('/api/v1/global/admin/dashboard', $this->token($admin))->assertOk()->assertJsonPath('data.totals.donations', 1000);
        $this->getJson('/api/v1/organization/public/'.$org->getKey())->assertOk()->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.bank')->assertJsonMissingPath('data.isAdmin');
        $this->getJson('/api/v1/donation/project/'.$p->getKey(), $headers)->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/organization/block/'.$org->getKey(), [], $headers)->assertForbidden();
        $this->deleteJson('/api/v1/organization/delete/'.$org->getKey(), [], $headers)->assertForbidden();
        $this->deleteJson('/api/v1/organization/delete/'.$admin->getKey(), [], $this->token($admin))->assertStatus(409);
        $this->deleteJson('/api/v1/organization/delete/'.$org->getKey(), [], $this->token($admin))->assertOk();
        $this->assertNotNull($org->fresh());
        $this->getJson('/api/v1/project/'.$p->getKey())->assertNotFound();
    }
}

<?php

namespace Tests\Feature;

use App\Models\WithdrawalRequest;
use App\Services\WithdrawalService;
use Tests\MongoTestCase;

class WithdrawalsTest extends MongoTestCase
{
    public function test_reservations_aliases_completion_and_cancellation(): void
    {
        $org = $this->organization();
        $admin = $this->organization(['isAdmin' => true]);
        $bank = $this->bank($org);
        $this->wallet($org)->update(['balance' => 5000]);
        $this->postJson('/api/v1/withdraw', ['amount' => 1000, 'bankId' => (string) $bank->getKey()], $this->token($org))->assertCreated();
        $w = WithdrawalRequest::firstOrFail();
        $this->assertSame(1000.0, $this->wallet($org)->reserved_balance);
        $this->postJson('/api/v1/withdrawal', ['amount' => 1000, 'bankId' => (string) $bank->getKey()], $this->token($org))->assertStatus(409);
        $this->getJson('/api/v1/withdrawal/single/'.$w->getKey(), $this->token($org))->assertOk();
        foreach (['in-progress', 'completed', 'completed'] as $status) {
            $this->patchJson('/api/v1/withdraw/admin/update/'.$w->getKey(), ['status' => $status], $this->token($admin))->assertOk();
        }
        $this->assertSame(4000.0, $this->wallet($org)->balance);
        $this->assertSame(0.0, $this->wallet($org)->reserved_balance);
        $this->postJson('/api/v1/withdrawal', ['amount' => 2000, 'bankId' => (string) $bank->getKey()], $this->token($org))->assertCreated();
        $pending = WithdrawalRequest::where('status', 'pending')->firstOrFail();
        $this->patchJson('/api/v1/withdrawal/admin/update/'.$pending->getKey(), ['status' => 'cancelled'], $this->token($admin))->assertOk();
        $this->assertSame(0.0, $this->wallet($org)->reserved_balance);
        $this->assertSame(4000.0, $this->wallet($org)->balance);
        $this->patchJson('/api/v1/withdrawal/admin/update/'.$pending->getKey(), ['status' => 'completed'], $this->token($admin))->assertStatus(409);
    }

    public function test_ownership_and_insufficient_funds(): void
    {
        $org = $this->organization();
        $other = $this->organization();
        $bank = $this->bank($org);
        $this->wallet($org)->update(['balance' => 500]);
        $this->postJson('/api/v1/withdraw', ['amount' => 1000, 'bankId' => (string) $bank->getKey()], $this->token($org))->assertStatus(409);
        $this->postJson('/api/v1/withdraw', ['amount' => 100, 'bankId' => (string) $bank->getKey()], $this->token($other))->assertForbidden();
        $this->postJson('/api/v1/withdraw', ['amount' => 100, 'bankId' => (string) $bank->getKey()], $this->token($org))->assertCreated();
        $w = WithdrawalRequest::firstOrFail();
        $this->getJson('/api/v1/withdraw/single/'.$w->getKey(), $this->token($other))->assertForbidden();
        $this->patchJson('/api/v1/withdraw/admin/update/'.$w->getKey(), ['status' => 'completed'], $this->token($org))->assertForbidden();
    }

    public function test_legacy_pending_withdrawal_is_adopted_without_double_debit(): void
    {
        $org = $this->organization();
        $this->wallet($org)->update(['balance' => 2000]);
        $w = WithdrawalRequest::create(['organization' => $org->getKey(), 'amount' => 1000, 'reference' => 'legacy-pending']);
        app(WithdrawalService::class)->transition((string) $w->getKey(), 'completed');
        $this->assertSame(1000.0, $this->wallet($org)->balance);
        $this->assertSame(0.0, $this->wallet($org)->reserved_balance);
    }
}

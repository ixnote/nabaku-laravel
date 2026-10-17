<?php

namespace Tests\Feature;

use App\Models\Outbox;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use Tests\MongoTestCase;

class PaymentsTest extends MongoTestCase
{
    private function fakePayment($trx, array $changes = []): array
    {
        config(['services.flutterwave.secret' => 'test-provider-key', 'services.flutterwave.hash' => 'test-webhook-hash']);
        $verified = ['id' => 12345, 'tx_ref' => $trx->tx_ref, 'currency' => 'NGN', 'amount' => $trx->amount, 'status' => 'successful', ...$changes];
        Http::fake(['*transactions/*' => Http::response(['status' => 'success', 'data' => $verified])]);

        return $verified;
    }

    public function test_duplicate_webhooks_credit_once_and_rollback_on_failure(): void
    {
        $org = $this->organization();
        $p = $this->project($org);
        $t = $this->transaction($org, $p);
        $this->fakePayment($t);
        $payload = ['data' => ['id' => 12345, 'tx_ref' => $t->tx_ref]];
        $headers = ['verif-hash' => 'test-webhook-hash'];
        $this->postJson('/api/v1/donation/webhook', $payload, $headers)->assertOk();
        $this->postJson('/api/v1/donation/webhook', $payload, $headers)->assertOk();
        $this->assertSame(1000.0, $this->wallet($org)->balance);
        $this->assertSame(1000.0, $p->fresh()->donated_amount);
        $this->assertSame(1, Outbox::count());
        $second = $this->transaction($org, $p);
        $verified = $this->fakePayment($second, ['id' => 23456]);
        $this->wallet($org)->delete();
        try {
            app(PaymentService::class)->settle($verified);
            $this->fail('Expected missing wallet');
        } catch (ModelNotFoundException $e) {
        }
        $this->assertSame('initiated', $second->fresh()->status);
        $this->assertSame(1000.0, $p->fresh()->donated_amount);
    }

    public function test_invalid_signature_and_amount_mismatch_do_not_credit(): void
    {
        $org = $this->organization();
        $p = $this->project($org);
        $t = $this->transaction($org, $p);
        $this->fakePayment($t, ['amount' => 9999]);
        $body = ['data' => ['id' => 12345, 'tx_ref' => $t->tx_ref]];
        $this->postJson('/api/v1/donation/webhook', $body, ['verif-hash' => 'wrong'])->assertUnauthorized();
        Http::assertNothingSent();
        $this->postJson('/api/v1/donation/webhook', $body, ['verif-hash' => 'test-webhook-hash'])->assertStatus(409);
        $this->assertSame(0.0, $this->wallet($org)->balance);
        $this->assertSame('initiated', $t->fresh()->status);
    }

    public function test_checkout_keeps_reference_when_provider_times_out(): void
    {
        $org = $this->organization();
        $p = $this->project($org);
        config(['services.flutterwave.secret' => 'test']);
        Http::fake(['*' => Http::failedConnection()]);
        $this->postJson('/api/v1/donation/make-donation', ['email' => 'donor@example.test', 'amount' => 1000, 'projectId' => (string) $p->getKey(), 'anonymous' => true])->assertStatus(502);
        $t = Transaction::firstOrFail();
        $this->assertSame('unknown', $t->checkoutState);
        $this->assertSame('initiated', $t->status);
        $this->assertSame(0.0, $this->wallet($org)->balance);
        Http::assertSentCount(1);
    }

    public function test_successful_checkout_and_no_downgrade_after_settlement(): void
    {
        $org = $this->organization();
        $p = $this->project($org);
        config(['services.flutterwave.secret' => 'test']);
        Http::fake(['*payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.example.test/pay']])]);
        $this->postJson('/api/v1/donation/make-donation', ['email' => 'donor@example.test', 'amount' => 1000, 'projectId' => (string) $p->getKey(), 'anonymous' => true])->assertOk()->assertJsonPath('data.link', 'https://checkout.example.test/pay');
        $t = Transaction::firstOrFail();
        $v = ['id' => 123, 'tx_ref' => $t->tx_ref, 'amount' => 1000, 'currency' => 'NGN', 'status' => 'successful'];
        app(PaymentService::class)->settle($v);
        app(PaymentService::class)->settle([...$v, 'status' => 'failed']);
        $this->assertSame('successful', $t->fresh()->status);
        $this->assertSame(1000.0, $this->wallet($org)->balance);
    }
}

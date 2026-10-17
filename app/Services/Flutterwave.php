<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class Flutterwave
{
    private function call(string $method, string $path, array $data = []): array
    {
        abort_unless(config('services.flutterwave.secret'), 503, 'Payment provider is not configured');
        try {
            $response = Http::baseUrl(config('services.flutterwave.url'))->withToken(config('services.flutterwave.secret'))->acceptJson()->connectTimeout(5)->timeout(30)->send($method, $path, [$method === 'GET' ? 'query' : 'json' => $data]);
        } catch (ConnectionException $e) {
            abort(502, 'Payment provider unavailable; reconcile the reference before retrying.');
        }
        abort_unless($response->successful() && $response->json('status') === 'success', 502, 'Payment provider request failed');

        return $response->json();
    }

    public function checkout(array $payload): array
    {
        return $this->call('POST', 'payments', $payload);
    }

    public function verify(string $id): array
    {
        abort_unless(ctype_digit($id), 400, 'Invalid provider transaction');

        return $this->call('GET', 'transactions/'.$id.'/verify')['data'];
    }

    public function verifyReference(string $ref): array
    {
        return $this->call('GET', 'transactions/verify_by_reference', ['tx_ref' => $ref])['data'];
    }

    public function banks(): array
    {
        return $this->call('GET', 'banks/NG');
    }

    public function resolve(array $payload): array
    {
        return $this->call('POST', 'accounts/resolve', $payload);
    }
}

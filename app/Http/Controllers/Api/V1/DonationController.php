<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DonationRequest;
use App\Models\Project;
use App\Models\Transaction;
use App\Services\Flutterwave;
use App\Services\PaymentService;
use App\Support\Access;
use App\Support\Api;
use App\Support\Ids;
use Illuminate\Http\Request;

class DonationController extends Controller
{
    public function create(DonationRequest $r, PaymentService $payments)
    {
        $v = $r->validated();

        return response()->json($payments->checkout($v));
    }

    public function webhook(Request $r, Flutterwave $flw, PaymentService $payments)
    {
        $secret = config('services.flutterwave.hash');
        abort_unless(is_string($secret) && $secret !== '', 503, 'Webhook is not configured');
        $mode = config('services.flutterwave.signature_mode');
        if ($mode === 'legacy') {
            $valid = hash_equals($secret, (string) $r->header('verif-hash'));
        } elseif ($mode === 'hmac') {
            $valid = hash_equals(base64_encode(hash_hmac('sha256', $r->getContent(), $secret, true)), (string) $r->header('flutterwave-signature'));
        } else {
            $valid = false;
        }
        abort_unless($valid, 401, 'Invalid webhook signature');
        $data = $r->input('data', $r->all());
        abort_unless(is_array($data), 400, 'Invalid webhook');
        $reference = $data['tx_ref'] ?? $data['txRef'] ?? null;
        abort_unless(is_string($reference) && strlen($reference) <= 200, 400, 'Missing payment reference');
        // Authenticate before lookup; verify remotely before any financial update.
        $trx = Transaction::where('tx_ref', $reference)->firstOrFail();
        $verified = isset($data['id']) ? $flw->verify((string) $data['id']) : $flw->verifyReference($reference);
        abort_unless(($verified['tx_ref'] ?? $verified['txRef'] ?? null) === $trx->tx_ref, 409, 'Payment reference mismatch');
        $payments->settle($verified);

        return response('', 200);
    }

    public function index(Request $r)
    {
        return $this->listing($r, Transaction::where('organization', Ids::object(Access::organization($r)))->where('status', 'successful'));
    }

    public function project(Request $r, string $projectId)
    {
        $p = Access::find(Project::class, $projectId);
        Access::owns($r, $p->organization);

        return $this->listing($r, Transaction::where('project', Ids::object($projectId))->where('status', 'successful'));
    }

    private function listing(Request $r, $q)
    {
        return response()->json(Api::page(Api::filters($q, $r, ['fullname', 'anonymous']), $r, function ($t) {
            $d = $t->toArray();
            $p = Project::find((string) $t->project);
            $d['project'] = $p ? ['_id' => (string) $p->getKey(), 'title' => $p->title] : null;

            return $d;
        }));
    }

    public function show(Request $r, string $donationId)
    {
        $t = Access::find(Transaction::class, $donationId);
        Access::owns($r, $t->organization);

        return Api::ok($t, 'Donation fetched successfully');
    }
}

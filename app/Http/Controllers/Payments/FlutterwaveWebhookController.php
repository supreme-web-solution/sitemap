<?php

namespace App\Http\Controllers\Payments;

use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Models\PaymentAccount;
use App\Services\Payments\FlutterwaveGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FlutterwaveWebhookController extends Controller
{
    /**
     * Receive Flutterwave events for one connected account.
     */
    public function __invoke(Request $request, string $key, FlutterwaveGateway $gateway): Response
    {
        $account = PaymentAccount::query()->where('webhook_key', $key)->where('provider', PaymentProvider::Flutterwave)->firstOrFail();
        $secret = $account->credential('webhook_hash') ?? $account->credential('secret_key');

        abort_if($secret === null, 400, 'Webhook hash is not set.');
        abort_unless(hash_equals($secret, (string) $request->header('verif-hash')), 400, 'Invalid signature.');

        $data = $request->json('data') ?? [];
        $invoiceId = $data['meta']['invoice_id'] ?? $data['tx_ref'] ?? null;

        if (is_string($invoiceId) && str_starts_with($invoiceId, 'inv-')) {
            $id = (int) explode('-', $invoiceId)[1];
            $invoice = $account->team->invoices()->find($id);
        } else {
            $invoice = $account->team->invoices()->find((int) $invoiceId);
        }

        if ($invoice !== null) {
            $gateway->settle($invoice, is_array($data) ? $data : []);
        }

        return response('ok');
    }
}

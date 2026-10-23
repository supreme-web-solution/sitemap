<?php

namespace App\Http\Controllers\Payments;

use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Models\PaymentAccount;
use App\Services\Payments\PaystackGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaystackWebhookController extends Controller
{
    /**
     * Receive Paystack events for one connected account.
     */
    public function __invoke(Request $request, string $key, PaystackGateway $gateway): Response
    {
        $account = PaymentAccount::query()->where('webhook_key', $key)->where('provider', PaymentProvider::Paystack)->firstOrFail();
        $secret = $account->credential('secret_key');

        abort_if($secret === null, 400, 'Secret key is not set.');

        $signature = (string) $request->header('X-Paystack-Signature');
        $expected = hash_hmac('sha512', $request->getContent(), $secret);

        abort_unless($signature !== '' && hash_equals($expected, $signature), 400, 'Invalid signature.');

        $event = $request->json()->all();

        if (($event['event'] ?? '') === 'charge.success') {
            $invoice = $account->team->invoices()->find($event['data']['metadata']['invoice_id'] ?? 0);

            if ($invoice !== null) {
                $gateway->settle($invoice, $event['data'] ?? []);
            }
        }

        return response('ok');
    }
}

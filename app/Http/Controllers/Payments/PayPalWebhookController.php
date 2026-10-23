<?php

namespace App\Http\Controllers\Payments;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Models\PaymentAccount;
use App\Services\Payments\PaymentException;
use App\Services\Payments\PayPalGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    /**
     * Receive PayPal events for one connected account and finish payments the customer approved.
     */
    public function __invoke(Request $request, string $key, PayPalGateway $gateway): Response
    {
        $account = PaymentAccount::query()->where('webhook_key', $key)->where('provider', PaymentProvider::PayPal)->firstOrFail();

        abort_if($account->credential('webhook_id') === null, 400, 'Webhook ID is not set.');
        abort_unless($gateway->hasValidWebhookSignature($account, $request), 400, 'Invalid signature.');

        $event = $request->json()->all();
        $resource = $event['resource'] ?? [];

        $orderId = match ($event['event_type'] ?? '') {
            'CHECKOUT.ORDER.APPROVED' => $resource['id'] ?? null,
            'PAYMENT.CAPTURE.COMPLETED' => $resource['supplementary_data']['related_ids']['order_id'] ?? null,
            default => null,
        };

        if (is_string($orderId) && $orderId !== '') {
            $invoice = $account->team->invoices()
                ->where('pending_reference', $orderId)
                ->where('status', InvoiceStatus::Open)
                ->with('lead')
                ->first();

            if ($invoice !== null) {
                try {
                    $gateway->completeOrder($invoice, $account, $orderId);
                } catch (PaymentException $exception) {
                    Log::warning('Could not finish a PayPal order from a webhook.', ['invoice_id' => $invoice->id, 'message' => $exception->getMessage()]);

                    abort(503, 'Try again later.');
                }
            }
        }

        return response('ok');
    }
}

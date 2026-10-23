<?php

namespace App\Http\Controllers\Payments;

use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Models\PaymentAccount;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StripeWebhookController extends Controller
{
    /**
     * How far a webhook's timestamp may be from now, in seconds.
     */
    protected const TOLERANCE = 300;

    /**
     * Receive Stripe events for one connected account and mark invoices paid.
     */
    public function __invoke(Request $request, string $key, StripeGateway $gateway): Response
    {
        $account = PaymentAccount::query()->where('webhook_key', $key)->where('provider', PaymentProvider::Stripe)->firstOrFail();
        $secret = $account->credential('webhook_secret');

        abort_if($secret === null, 400, 'Webhook signing secret is not set.');
        abort_unless($this->hasValidSignature($request, $secret), 400, 'Invalid signature.');

        $event = $request->json()->all();
        $session = $event['data']['object'] ?? [];

        if (in_array($event['type'] ?? '', ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            $invoice = $account->team->invoices()->find($session['metadata']['invoice_id'] ?? 0);

            if ($invoice !== null) {
                $gateway->settle($invoice, $session);
            }
        }

        return response('ok');
    }

    protected function hasValidSignature(Request $request, string $secret): bool
    {
        $parts = [];

        foreach (explode(',', (string) $request->header('Stripe-Signature')) as $piece) {
            [$name, $value] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[$name][] = $value;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);

        if ($timestamp === 0 || abs(time() - $timestamp) > self::TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        foreach ($parts['v1'] ?? [] as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}

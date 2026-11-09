<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\MapmaticPlanCheckout;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MapmaticStripeWebhookController extends Controller
{
    protected const TOLERANCE = 300;

    /**
     * Receive platform Stripe events for MapMatic team plan checkouts.
     */
    public function __invoke(Request $request, MapmaticPlanCheckout $checkout): Response
    {
        $secret = config('services.billing.stripe_webhook_secret');

        abort_if(blank($secret), 400, 'Webhook signing secret is not set.');
        abort_unless($this->hasValidSignature($request, (string) $secret), 400, 'Invalid signature.');

        $event = $request->json()->all();
        $session = $event['data']['object'] ?? [];

        if (in_array($event['type'] ?? '', ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            $checkout->applyCheckoutSession(is_array($session) ? $session : []);
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

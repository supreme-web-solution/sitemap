<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Platform Stripe Checkout for MapMatic team plan upgrades (not client invoice payments).
 */
class MapmaticPlanCheckout
{
    public function configured(): bool
    {
        return filled(config('services.billing.stripe_secret'));
    }

    /**
     * Create a Stripe Checkout Session URL for upgrading the team plan.
     */
    public function checkoutUrl(Team $team, Plan $plan, User $user): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('MapMatic billing is not configured.');
        }

        if (! filled($plan->stripe_price_id)) {
            throw new RuntimeException('This plan is not linked to a Stripe price.');
        }

        $success = route('plan.index', ['current_team' => $team->slug, 'upgraded' => 1]);
        $cancel = route('plan.index', ['current_team' => $team->slug, 'canceled' => 1]);

        $response = $this->client()->asForm()->post('/checkout/sessions', [
            'mode' => 'subscription',
            'success_url' => $success,
            'cancel_url' => $cancel,
            'client_reference_id' => (string) $team->id,
            'customer_email' => $user->email,
            'line_items' => [
                ['price' => $plan->stripe_price_id, 'quantity' => 1],
            ],
            'metadata' => [
                'team_id' => (string) $team->id,
                'plan_id' => (string) $plan->id,
            ],
            'subscription_data' => [
                'metadata' => [
                    'team_id' => (string) $team->id,
                    'plan_id' => (string) $plan->id,
                ],
            ],
        ]);

        if (! $response->successful() || blank($response->json('url'))) {
            throw new RuntimeException($response->json('error.message') ?? 'Stripe Checkout could not be started.');
        }

        return (string) $response->json('url');
    }

    /**
     * Apply a paid plan after Stripe confirms checkout.
     *
     * @param  array<string, mixed>  $session
     */
    public function applyCheckoutSession(array $session): void
    {
        if (($session['payment_status'] ?? null) !== 'paid' && ($session['status'] ?? null) !== 'complete') {
            return;
        }

        $teamId = (int) data_get($session, 'metadata.team_id', $session['client_reference_id'] ?? 0);
        $planId = (int) data_get($session, 'metadata.plan_id', 0);

        if ($teamId < 1 || $planId < 1) {
            return;
        }

        $team = Team::query()->find($teamId);
        $plan = Plan::query()->whereKey($planId)->where('is_active', true)->first();

        if ($team === null || $plan === null) {
            return;
        }

        $team->forceFill(['plan_id' => $plan->id])->save();
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl('https://api.stripe.com/v1')
            ->withBasicAuth((string) config('services.billing.stripe_secret'), '')
            ->acceptJson();
    }
}

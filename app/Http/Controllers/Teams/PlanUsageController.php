<?php

namespace App\Http\Controllers\Teams;

use App\Enums\PlanFeature;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Team;
use App\Services\Billing\MapmaticPlanCheckout;
use App\Services\Billing\UsageMeter;
use Inertia\Inertia;
use Inertia\Response;

class PlanUsageController extends Controller
{
    /**
     * Show the team's plan, available upgrades, usage, and features.
     */
    public function __invoke(Team $currentTeam, UsageMeter $usage, MapmaticPlanCheckout $checkout): Response
    {
        $plan = $currentTeam->effectivePlan();
        $role = request()->user()?->teamRole($currentTeam);
        $canChangePlan = $role !== null && $role->isAtLeast(TeamRole::Admin);

        return Inertia::render('plan/Index', [
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => $plan->price_cents === 0 ? 'Free' : Invoice::formatCents($plan->price_cents).'/mo',
                'priceCents' => $plan->price_cents,
            ] : null,
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Plan $option) => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'description' => $option->description,
                    'price' => $option->price_cents === 0 ? 'Free' : Invoice::formatCents($option->price_cents).'/mo',
                    'priceCents' => $option->price_cents,
                    'isCurrent' => $plan?->is($option) ?? false,
                    'requiresCheckout' => $option->price_cents > 0,
                    'checkoutReady' => $option->price_cents > 0
                        && $checkout->configured()
                        && filled($option->stripe_price_id),
                    'features' => collect(PlanFeature::cases())->map(fn (PlanFeature $feature) => [
                        'label' => $feature->label(),
                        'included' => $option->allows($feature),
                    ])->values(),
                ]),
            'canChangePlan' => $canChangePlan,
            'billingConfigured' => $checkout->configured(),
            'usage' => $usage->snapshot($currentTeam),
            'features' => collect(PlanFeature::cases())->map(fn (PlanFeature $feature) => [
                'label' => $feature->label(),
                'included' => $usage->allows($currentTeam, $feature),
            ])->values(),
        ]);
    }
}

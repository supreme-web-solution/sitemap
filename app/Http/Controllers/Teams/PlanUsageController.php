<?php

namespace App\Http\Controllers\Teams;

use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use Inertia\Inertia;
use Inertia\Response;

class PlanUsageController extends Controller
{
    /**
     * Show the team's plan, what it includes and how much has been used.
     */
    public function __invoke(Team $currentTeam, UsageMeter $usage): Response
    {
        $plan = $currentTeam->effectivePlan();

        return Inertia::render('plan/Index', [
            'plan' => $plan ? [
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => $plan->price_cents === 0 ? 'Free' : Invoice::formatCents($plan->price_cents).'/mo',
            ] : null,
            'usage' => $usage->snapshot($currentTeam),
            'features' => collect(PlanFeature::cases())->map(fn (PlanFeature $feature) => [
                'label' => $feature->label(),
                'included' => $usage->allows($currentTeam, $feature),
            ])->values(),
        ]);
    }
}

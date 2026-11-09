<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Team;
use App\Services\Billing\MapmaticPlanCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class PlanChangeController extends Controller
{
    /**
     * Switch the team plan, or start Stripe Checkout for a paid plan.
     */
    public function __invoke(Request $request, Team $currentTeam, Plan $plan, MapmaticPlanCheckout $checkout): RedirectResponse
    {
        $role = $request->user()->teamRole($currentTeam);

        abort_unless($role !== null && $role->isAtLeast(TeamRole::Admin), 403);
        abort_unless($plan->is_active, 404);

        if ($currentTeam->plan_id === $plan->id) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('You are already on :plan.', ['plan' => $plan->name])]);

            return back();
        }

        if ($plan->price_cents > 0) {
            if ($checkout->configured() && filled($plan->stripe_price_id)) {
                try {
                    return redirect()->away($checkout->checkoutUrl($currentTeam, $plan, $request->user()));
                } catch (RuntimeException $exception) {
                    Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

                    return back();
                }
            }

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Paid plan checkout is not available yet. Ask a platform admin to assign :plan.', [
                    'plan' => $plan->name,
                ]),
            ]);

            return back();
        }

        $currentTeam->forceFill(['plan_id' => $plan->id])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Switched to the :plan plan.', ['plan' => $plan->name]),
        ]);

        return to_route('plan.index', ['current_team' => $currentTeam->slug]);
    }
}

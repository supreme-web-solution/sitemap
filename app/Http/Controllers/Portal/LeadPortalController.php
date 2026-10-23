<?php

namespace App\Http\Controllers\Portal;

use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LeadPortalController extends Controller
{
    /**
     * Switch the client portal on, or replace its link with a new one.
     */
    public function store(Team $currentTeam, Lead $lead, UsageMeter $usage): RedirectResponse
    {
        $usage->ensureFeature($currentTeam, PlanFeature::ClientPortal);

        $lead->forceFill(['portal_token' => Str::random(40)])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client portal link created.')]);

        return back();
    }

    /**
     * Switch the client portal off. The old link stops working.
     */
    public function destroy(Team $currentTeam, Lead $lead): RedirectResponse
    {
        $lead->forceFill(['portal_token' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client portal turned off.')]);

        return back();
    }
}
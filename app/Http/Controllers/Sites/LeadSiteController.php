<?php

namespace App\Http\Controllers\Sites;

use App\Actions\Sites\DispatchSiteGeneration;
use App\Enums\SiteGenerationStatus;
use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LeadSiteController extends Controller
{
    /**
     * Queue generation (or regeneration) of the website for a lead.
     */
    public function store(Team $currentTeam, Lead $lead, DispatchSiteGeneration $dispatchSiteGeneration, UsageMeter $usage): RedirectResponse
    {
        if ($lead->site()->doesntExist()) {
            $usage->ensureWithin($currentTeam, UsageMetric::Sites);
        }

        $usage->consume($currentTeam, UsageMetric::SiteGenerations);

        $lead->update([
            'site_generation_status' => SiteGenerationStatus::Queued,
            'site_generation_error' => null,
        ]);

        $dispatchSiteGeneration->handle($lead);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Building the website for :name.', ['name' => $lead->name])]);

        return back();
    }
}

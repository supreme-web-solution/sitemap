<?php

namespace App\Http\Controllers\Sites;

use App\Actions\Sites\ChooseSiteConcept;
use App\Actions\Sites\DispatchSiteConcepts;
use App\Enums\PlanFeature;
use App\Enums\SiteGenerationStatus;
use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\SiteConcept;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SiteConceptController extends Controller
{
    /**
     * Queue three design options for the lead, one per vibe.
     */
    public function store(Team $currentTeam, Lead $lead, DispatchSiteConcepts $dispatch, UsageMeter $usage): RedirectResponse
    {
        $usage->ensureFeature($currentTeam, PlanFeature::AiEditing);
        $usage->consume($currentTeam, UsageMetric::SiteGenerations);

        $conceptsStuck = $lead->concepts_status === SiteGenerationStatus::Queued
            && $lead->concepts_requested_at !== null
            && $lead->concepts_requested_at->lt(now()->subMinutes(2));

        if ($lead->concepts_status === SiteGenerationStatus::Queued && ! $conceptsStuck) {
            throw ValidationException::withMessages([
                'concepts' => __('Three designs are already being built.'),
            ]);
        }

        if ($lead->site_generation_status === SiteGenerationStatus::Queued) {
            throw ValidationException::withMessages([
                'concepts' => __('Wait until the website finishes building before generating designs.'),
            ]);
        }

        $dispatch->handle($lead);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Three design options are ready for :name.', ['name' => $lead->name]),
        ]);

        return back();
    }

    /**
     * Make one of the options the live website.
     */
    public function choose(Team $currentTeam, Lead $lead, SiteConcept $concept, ChooseSiteConcept $choose, UsageMeter $usage): RedirectResponse
    {
        $usage->ensureFeature($currentTeam, PlanFeature::AiEditing);

        if ($lead->site()->doesntExist()) {
            $usage->ensureWithin($currentTeam, UsageMetric::Sites);
        }

        $site = $choose->handle($lead, $concept, auth()->id());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('This design is now the website.')]);

        return redirect()->route('sites.edit', ['current_team' => $currentTeam, 'site' => $site]);
    }
}

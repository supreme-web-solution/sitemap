<?php

namespace App\Http\Controllers\Leads;

use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Jobs\EnrichLeadFromWebsite;
use App\Models\Lead;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LeadEnrichmentController extends Controller
{
    /**
     * Queue a safe fetch of public contact hints from the lead website.
     */
    public function store(Team $currentTeam, Lead $lead, UsageMeter $usage): RedirectResponse
    {
        if (blank($lead->website)) {
            throw ValidationException::withMessages([
                'website' => __('Add a website URL before enriching this lead.'),
            ]);
        }

        $usage->consume($currentTeam, UsageMetric::Audits);

        EnrichLeadFromWebsite::dispatch($lead);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Enrichment queued from the lead website.')]);

        return back();
    }
}

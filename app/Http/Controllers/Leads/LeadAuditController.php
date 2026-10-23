<?php

namespace App\Http\Controllers\Leads;

use App\Enums\SiteGenerationStatus;
use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Jobs\AuditLeadWebsite;
use App\Models\Lead;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LeadAuditController extends Controller
{
    /**
     * Queue an audit of the lead's existing website.
     */
    public function store(Team $currentTeam, Lead $lead, UsageMeter $usage): RedirectResponse
    {
        if (blank($lead->website)) {
            throw ValidationException::withMessages([
                'website' => __('This lead has no website to audit.'),
            ]);
        }

        $usage->consume($currentTeam, UsageMetric::Audits);

        $lead->update(['website_audit_status' => SiteGenerationStatus::Queued]);

        AuditLeadWebsite::dispatch($lead);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Auditing the website now.')]);

        return back();
    }
}

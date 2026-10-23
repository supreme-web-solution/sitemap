<?php

namespace App\Http\Controllers\Leads;

use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Team;
use App\Services\Audit\UnsafeUrlException;
use App\Services\Audit\WebsiteAuditor;
use App\Services\Billing\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompetitorAuditController extends Controller
{
    /**
     * Audit a competitor URL and store the report on the lead.
     */
    public function store(Request $request, Team $currentTeam, Lead $lead, WebsiteAuditor $auditor, UsageMeter $usage): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:500'],
        ]);

        $usage->consume($currentTeam, UsageMetric::Audits);

        try {
            $report = $auditor->audit($data['url']);
        } catch (UnsafeUrlException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        $lead->forceFill([
            'competitor_audit' => $report,
            'competitor_audited_at' => now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Competitor site audited.')]);

        return back();
    }
}

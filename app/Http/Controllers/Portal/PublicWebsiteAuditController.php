<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\Audit\PublicWebsiteAuditPresenter;
use Illuminate\View\View;

class PublicWebsiteAuditController extends Controller
{
    /**
     * Show a shareable website audit report for the business owner.
     */
    public function show(Lead $lead, PublicWebsiteAuditPresenter $presenter): View
    {
        abort_unless(filled($lead->website_audit_public_token), 404);
        abort_unless(filled($lead->website_audit), 404);

        $audit = $lead->website_audit;

        abort_unless(blank($audit['error'] ?? null), 404);
        $checks = collect($audit['checks'] ?? [])
            ->map(fn (array $check) => $presenter->present($check))
            ->values()
            ->all();

        $issues = collect($checks)->whereIn('status', ['fail', 'warn'])->count();

        return view('audit.public', [
            'lead' => $lead->load('team'),
            'audit' => $audit,
            'checks' => $checks,
            'issues' => $issues,
        ]);
    }
}

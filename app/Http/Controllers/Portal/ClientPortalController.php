<?php

namespace App\Http\Controllers\Portal;

use App\Enums\InvoiceStatus;
use App\Enums\LeadActivityType;
use App\Enums\PlanFeature;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\Billing\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientPortalController extends Controller
{
    /**
     * Show the client their website, proposals and invoices.
     */
    public function show(Lead $lead, UsageMeter $usage): View
    {
        $this->ensureAvailable($lead, $usage);

        $site = $lead->site;

        return view('portal.show', [
            'lead' => $lead->load('team'),
            'site' => $site,
            'previewUrl' => $site ? route('sites.preview', $site->slug) : null,
            'proposals' => $lead->proposals()->whereIn('status', [ProposalStatus::Sent, ProposalStatus::Accepted])->latest('id')->get(),
            'invoices' => $lead->invoices()
                ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Paid])
                ->whereNotNull('sent_at')
                ->latest('id')
                ->get(),
        ]);
    }

    /**
     * The client approves the website.
     */
    public function approve(Lead $lead, UsageMeter $usage): RedirectResponse
    {
        $this->ensureAvailable($lead, $usage);

        $site = $lead->site;

        abort_if($site === null, 404);

        if ($site->approved_at === null) {
            $site->forceFill(['approved_at' => now()])->save();

            $lead->recordActivity(LeadActivityType::SiteApproved, __('The client approved the website.'));
        }

        return back()->with(['notice' => __('Thank you! The website is approved.'), 'notice_type' => 'ok']);
    }

    /**
     * The client leaves a note, which lands on the lead's timeline and in today's follow-ups.
     */
    public function feedback(Request $request, Lead $lead, UsageMeter $usage): RedirectResponse
    {
        $this->ensureAvailable($lead, $usage);

        $validated = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        $lead->recordActivity(LeadActivityType::ClientFeedback, $validated['message']);
        $lead->update(['next_follow_up_at' => today()]);

        return back()->with(['notice' => __('Thanks, your feedback was sent.'), 'notice_type' => 'ok']);
    }

    /**
     * The portal only works while the agency's plan includes it.
     */
    private function ensureAvailable(Lead $lead, UsageMeter $usage): void
    {
        abort_unless($usage->allows($lead->team, PlanFeature::ClientPortal), 404);
    }
}
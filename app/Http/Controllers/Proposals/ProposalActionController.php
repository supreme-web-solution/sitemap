<?php

namespace App\Http\Controllers\Proposals;

use App\Enums\LeadActivityType;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Mail\ProposalEmail;
use App\Models\Proposal;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProposalActionController extends Controller
{
    /**
     * Make the proposal visible to the client, optionally emailing the link.
     */
    public function send(Request $request, Team $currentTeam, Proposal $proposal): RedirectResponse
    {
        if (! in_array($proposal->status, [ProposalStatus::Draft, ProposalStatus::Sent, ProposalStatus::Declined], true)) {
            throw ValidationException::withMessages(['proposal' => __('This proposal was already accepted.')]);
        }

        if ($proposal->items()->doesntExist()) {
            throw ValidationException::withMessages(['proposal' => __('Add at least one item before sending.')]);
        }

        $emailing = $request->boolean('email');
        $lead = $proposal->lead;

        if ($emailing && blank($lead->email)) {
            throw ValidationException::withMessages(['proposal' => __('This lead has no email address. Copy the link and send it yourself, or add an email first.')]);
        }

        $proposal->forceFill(['status' => ProposalStatus::Sent, 'sent_at' => now(), 'declined_at' => null])->save();

        if ($emailing) {
            Mail::to($lead->email)->queue(new ProposalEmail($proposal, $request->user()->name, $request->user()->email));
        }

        $lead->recordActivity(
            LeadActivityType::ProposalSent,
            __('Proposal :number sent', ['number' => $proposal->number]).($emailing ? __(' and emailed') : ''),
            ['proposal_id' => $proposal->id],
            $request->user()->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $emailing ? __('Proposal emailed to :email.', ['email' => $lead->email]) : __('Proposal is ready. Copy the link to share it.')]);

        return back();
    }
}
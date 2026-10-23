<?php

namespace App\Http\Controllers\Proposals;

use App\Actions\Proposals\AcceptProposal;
use App\Enums\LeadActivityType;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Proposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProposalPublicController extends Controller
{
    /**
     * Show the proposal to the client.
     */
    public function show(Proposal $proposal): View
    {
        abort_if($proposal->status === ProposalStatus::Draft, 404);

        if ($proposal->viewed_at === null) {
            $proposal->forceFill(['viewed_at' => now()])->save();
        }

        return view('proposals.public', ['proposal' => $proposal->load(['team', 'lead', 'items', 'invoice'])]);
    }

    /**
     * Accept the proposal and go to the first invoice.
     */
    public function accept(Request $request, Proposal $proposal, AcceptProposal $acceptProposal): RedirectResponse
    {
        abort_if($proposal->status === ProposalStatus::Draft, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'agree' => ['accepted'],
        ], ['agree.accepted' => __('Please confirm that you agree.')]);

        if (! $proposal->isOpen()) {
            return back()->with(['notice' => __('This proposal can no longer be accepted.'), 'notice_type' => 'warn']);
        }

        $invoice = $acceptProposal->handle($proposal, $validated['name']);

        if ($invoice !== null) {
            return redirect()->to($invoice->payUrl());
        }

        return back()->with(['notice' => __('Thank you! Your proposal is accepted.'), 'notice_type' => 'ok']);
    }

    /**
     * Decline the proposal.
     */
    public function decline(Proposal $proposal): RedirectResponse
    {
        abort_if($proposal->status === ProposalStatus::Draft, 404);

        if (! $proposal->isOpen()) {
            return back()->with(['notice' => __('This proposal can no longer be changed.'), 'notice_type' => 'warn']);
        }

        $proposal->forceFill(['status' => ProposalStatus::Declined, 'declined_at' => now()])->save();

        $proposal->lead->recordActivity(
            LeadActivityType::ProposalDeclined,
            __('Proposal :number declined by the client', ['number' => $proposal->number]),
            ['proposal_id' => $proposal->id],
        );

        return back()->with(['notice' => __('You declined this proposal. Thank you for letting us know.'), 'notice_type' => 'warn']);
    }
}
<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Models\FollowUpSequence;
use App\Models\Lead;
use App\Models\SequenceEnrollment;
use App\Models\Team;
use App\Services\Outreach\SequenceEnroller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SequenceEnrollmentController extends Controller
{
    public function __construct(public SequenceEnroller $enroller) {}

    /**
     * Start a follow-up sequence for this lead.
     */
    public function store(Request $request, Team $currentTeam, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'sequence_id' => ['required', 'integer', Rule::exists('follow_up_sequences', 'id')->where('team_id', $currentTeam->id)],
        ]);

        $sequence = FollowUpSequence::query()->findOrFail($data['sequence_id']);

        $this->enroller->enroll($currentTeam, $lead, $sequence);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Enrolled in :name.', ['name' => $sequence->name])]);

        return back();
    }

    /**
     * Stop sending further emails in the sequence.
     */
    public function destroy(Team $currentTeam, Lead $lead, SequenceEnrollment $enrollment): RedirectResponse
    {
        $enrollment->stop(__('Stopped by the team.'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Removed from sequence.')]);

        return back();
    }
}

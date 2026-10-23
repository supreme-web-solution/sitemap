<?php

namespace App\Http\Controllers\Leads;

use App\Enums\EnrollmentStatus;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\FollowUpSequence;
use App\Models\Lead;
use App\Models\SequenceEnrollment;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SequenceEnrollmentController extends Controller
{
    /**
     * Start a follow-up sequence for this lead.
     */
    public function store(Request $request, Team $currentTeam, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'sequence_id' => ['required', 'integer', Rule::exists('follow_up_sequences', 'id')->where('team_id', $currentTeam->id)],
        ]);

        $sequence = FollowUpSequence::query()->findOrFail($data['sequence_id']);

        if (! $sequence->is_active) {
            throw ValidationException::withMessages(['sequence_id' => __('This sequence is switched off.')]);
        }

        if (in_array($lead->status, [LeadStatus::Won, LeadStatus::Lost], true)) {
            throw ValidationException::withMessages(['sequence_id' => __('Won and lost leads are not enrolled.')]);
        }

        if (blank($lead->email)) {
            throw ValidationException::withMessages(['sequence_id' => __('Add an email address first.')]);
        }

        $first = $sequence->steps()->orderBy('position')->first();

        if ($first === null) {
            throw ValidationException::withMessages(['sequence_id' => __('This sequence has no steps.')]);
        }

        $lead->enrollments()->updateOrCreate(
            ['follow_up_sequence_id' => $sequence->id],
            [
                'team_id' => $currentTeam->id,
                'status' => EnrollmentStatus::Active,
                'next_position' => $first->position,
                'next_step_at' => now()->addDays($first->delay_days),
                'stopped_reason' => null,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Enrolled in :name.', ['name' => $sequence->name])]);

        return back();
    }

    /**
     * Stop sending further emails in the sequence.
     */
    public function destroy(Team $currentTeam, Lead $lead, SequenceEnrollment $enrollment): RedirectResponse
    {
        $enrollment->stop(__('Stopped by the team.'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sequence stopped.')]);

        return back();
    }
}

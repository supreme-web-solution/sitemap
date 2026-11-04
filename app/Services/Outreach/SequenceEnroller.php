<?php

namespace App\Services\Outreach;

use App\Enums\EnrollmentStatus;
use App\Enums\LeadStatus;
use App\Models\FollowUpSequence;
use App\Models\Lead;
use App\Models\SequenceEnrollment;
use App\Models\Team;
use Illuminate\Validation\ValidationException;

class SequenceEnroller
{
    /**
     * Enroll the lead in the sequence or throw a validation error.
     */
    public function enroll(Team $team, Lead $lead, FollowUpSequence $sequence): SequenceEnrollment
    {
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

        return $lead->enrollments()->updateOrCreate(
            ['follow_up_sequence_id' => $sequence->id],
            [
                'team_id' => $team->id,
                'status' => EnrollmentStatus::Active,
                'next_position' => $first->position,
                'next_step_at' => now()->addDays($first->delay_days),
                'stopped_reason' => null,
            ],
        );
    }

    /**
     * Enroll the lead, or return a short reason the lead was skipped.
     */
    public function enrollOrSkip(Team $team, Lead $lead, FollowUpSequence $sequence): ?string
    {
        try {
            $this->enroll($team, $lead, $sequence);

            return null;
        } catch (ValidationException $exception) {
            return collect($exception->errors())->flatten()->first();
        }
    }
}

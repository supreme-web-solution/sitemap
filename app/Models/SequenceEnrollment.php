<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $lead_id
 * @property int $follow_up_sequence_id
 * @property EnrollmentStatus $status
 * @property int $next_position
 * @property Carbon|null $next_step_at
 * @property string|null $stopped_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lead $lead
 * @property-read FollowUpSequence $sequence
 */
#[Fillable(['team_id', 'lead_id', 'follow_up_sequence_id', 'status', 'next_position', 'next_step_at', 'stopped_reason'])]
class SequenceEnrollment extends Model
{
    /**
     * Enrollments that are due to send their next email.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::Active)
            ->whereNotNull('next_step_at')
            ->where('next_step_at', '<=', now());
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<FollowUpSequence, $this>
     */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(FollowUpSequence::class, 'follow_up_sequence_id');
    }

    /**
     * Stop sending further emails in this sequence.
     */
    public function stop(string $reason): void
    {
        if ($this->status !== EnrollmentStatus::Active) {
            return;
        }

        $this->forceFill([
            'status' => EnrollmentStatus::Stopped,
            'stopped_reason' => $reason,
            'next_step_at' => null,
        ])->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'next_position' => 'integer',
            'next_step_at' => 'datetime',
        ];
    }
}

<?php

namespace App\Observers;

use App\Enums\EnrollmentStatus;
use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Services\Prospecting\OpportunityScorer;
use App\Support\PhoneNumber;

class LeadObserver
{
    /**
     * Attributes that influence the opportunity score.
     *
     * @var list<string>
     */
    protected const SCORED_ATTRIBUTES = [
        'website', 'phone', 'email', 'address', 'category', 'city',
        'rating', 'reviews_count', 'website_audit',
    ];

    public function __construct(protected OpportunityScorer $scorer) {}

    /**
     * Keep the opportunity score in sync before the lead is saved.
     */
    public function saving(Lead $lead): void
    {
        if (! $lead->exists || $lead->isDirty('phone') || ($lead->phone !== null && $lead->phone_e164 === null)) {
            $lead->phone_e164 = PhoneNumber::e164($lead->phone);
        }

        if ($lead->exists && $lead->opportunity_score !== null && ! $lead->isDirty(self::SCORED_ATTRIBUTES)) {
            return;
        }

        $result = $this->scorer->score($lead);

        $lead->opportunity_score = $result['score'];
        $lead->opportunity_breakdown = $result['breakdown'];
    }

    /**
     * Record the pipeline stage change in the lead's timeline.
     */
    public function updated(Lead $lead): void
    {
        if ($lead->wasChanged('status')) {
            $lead->recordActivity(
                LeadActivityType::StatusChanged,
                __('Stage changed to :stage', ['stage' => $lead->status->label()]),
                ['from' => $lead->getRawOriginal('status'), 'to' => $lead->status->value],
                auth()->id(),
            );

            if (in_array($lead->status, [LeadStatus::Won, LeadStatus::Lost], true)) {
                $lead->enrollments()->where('status', EnrollmentStatus::Active)->each(
                    fn ($enrollment) => $enrollment->stop(__('The deal closed.')),
                );
            }
        }
    }
}

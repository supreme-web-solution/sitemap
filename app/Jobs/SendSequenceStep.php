<?php

namespace App\Jobs;

use App\Enums\EnrollmentStatus;
use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\UsageMetric;
use App\Mail\OutreachEmail;
use App\Models\SequenceEnrollment;
use App\Models\User;
use App\Services\Billing\UsageMeter;
use App\Services\Mail\OutboundThreadRecorder;
use App\Services\Outreach\MergeFieldRenderer;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendSequenceStep implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    public int $timeout = 45;

    public function __construct(public SequenceEnrollment $enrollment)
    {
        $this->onQueue(Queues::DEFAULT);
    }

    public function uniqueId(): string
    {
        return (string) $this->enrollment->id;
    }

    /**
     * Send the next email in the sequence, then schedule the following step.
     */
    public function handle(MergeFieldRenderer $merge, UsageMeter $usage, OutboundThreadRecorder $threads): void
    {
        $enrollment = $this->enrollment->fresh(['lead', 'sequence.steps', 'lead.team']);

        if ($enrollment === null || $enrollment->status !== EnrollmentStatus::Active) {
            return;
        }

        $lead = $enrollment->lead;

        if (in_array($lead->status, [LeadStatus::Won, LeadStatus::Lost], true)) {
            $enrollment->stop(__('The deal closed.'));

            return;
        }

        $step = $enrollment->sequence->steps->firstWhere('position', $enrollment->next_position);

        if ($step === null) {
            $enrollment->forceFill(['status' => EnrollmentStatus::Completed, 'next_step_at' => null])->save();

            return;
        }

        if (blank($lead->email) || ! $enrollment->sequence->is_active) {
            $enrollment->stop(__('The sequence could not send (missing email, or it was switched off).'));

            return;
        }

        $owner = $lead->team->owner();

        if (! $owner instanceof User) {
            $enrollment->stop(__('The sequence could not send (no team owner).'));

            return;
        }

        $usage->consume($lead->team, UsageMetric::Emails);

        $subject = $merge->render($step->subject, $lead, $owner);
        $body = $merge->render($step->body, $lead, $owner);

        $messageId = $threads->generateMessageId();
        $threads->record($lead, $subject, $messageId);

        Mail::to($lead->email)->queue(new OutreachEmail(
            $subject,
            $body,
            $owner->name,
            $owner->email,
            $messageId,
        ));

        $lead->recordActivity(
            LeadActivityType::EmailSent,
            __('Sequence email: :subject', ['subject' => $subject]),
            ['enrollment_id' => $enrollment->id, 'step' => $step->position],
        );

        $lead->forceFill([
            'status' => in_array($lead->status, [LeadStatus::New, LeadStatus::SiteGenerated], true)
                ? LeadStatus::Contacted
                : $lead->status,
            'last_contacted_at' => now(),
        ])->save();

        $next = $enrollment->sequence->steps->firstWhere('position', $step->position + 1);

        if ($next === null) {
            $enrollment->forceFill([
                'status' => EnrollmentStatus::Completed,
                'next_position' => $step->position + 1,
                'next_step_at' => null,
            ])->save();

            return;
        }

        $enrollment->forceFill([
            'next_position' => $next->position,
            'next_step_at' => now()->addDays($next->delay_days),
        ])->save();
    }
}

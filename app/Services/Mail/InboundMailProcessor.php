<?php

namespace App\Services\Mail;

use App\Enums\EnrollmentStatus;
use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\MailConnectionStatus;
use App\Models\EmailThread;
use App\Models\InboxMessage;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\MailConnection;
use App\Models\SequenceEnrollment;
use Illuminate\Support\Str;

class InboundMailProcessor
{
    /**
     * Log an inbound reply manually when mailbox sync is unavailable.
     */
    public function recordManual(Lead $lead, string $subject, string $snippet, ?int $userId = null): LeadActivity
    {
        $snippet = Str::limit($snippet, (int) config('inbox.manual_log_snippet_max_length'));
        $receivedAt = now();

        $thread = EmailThread::query()
            ->where('team_id', $lead->team_id)
            ->where('lead_id', $lead->id)
            ->where('subject_normalized', EmailSubjectNormalizer::normalize($subject))
            ->latest('last_outbound_at')
            ->first();

        $activity = $lead->recordActivity(
            LeadActivityType::EmailReceived,
            __('Reply logged: :subject', ['subject' => $subject]),
            [
                'subject' => $subject,
                'snippet' => $snippet,
                'manual' => true,
            ],
            $userId,
        );

        $lead->forceFill(['last_email_reply_at' => $receivedAt])->save();

        if ($thread !== null && $thread->replied_at === null) {
            $thread->forceFill(['replied_at' => $receivedAt])->save();
        }

        if (in_array($lead->status, [LeadStatus::Contacted, LeadStatus::SiteGenerated], true)) {
            $lead->forceFill(['status' => LeadStatus::Interested])->save();
        }

        $this->pauseActiveSequences($lead);

        return $activity;
    }

    /**
     * Import a single inbound message, match it to a lead, and record timeline activity.
     */
    public function import(MailConnection $connection, InboundMailMessage $message, bool $manual = false): ?InboxMessage
    {
        if ($this->alreadyImported($connection, $message->externalMessageId)) {
            return null;
        }

        $lead = $this->matchLead($connection, $message);

        $thread = $lead ? $this->matchThread($connection, $lead, $message) : null;

        $snippet = Str::limit($message->snippet, (int) config('inbox.snippet_max_length'));

        $activity = $lead?->recordActivity(
            LeadActivityType::EmailReceived,
            __('Reply received: :subject', ['subject' => $message->subject]),
            [
                'subject' => $message->subject,
                'snippet' => $snippet,
                'from' => $message->fromAddress,
                'message_id' => $message->externalMessageId,
                'manual' => $manual,
            ],
        );

        if ($lead !== null) {
            $lead->forceFill(['last_email_reply_at' => $message->receivedAt])->save();

            if ($thread !== null && $thread->replied_at === null) {
                $thread->forceFill(['replied_at' => $message->receivedAt])->save();
            }

            if (in_array($lead->status, [LeadStatus::Contacted, LeadStatus::SiteGenerated], true)) {
                $lead->forceFill(['status' => LeadStatus::Interested])->save();
            }

            $this->pauseActiveSequences($lead);
        }

        return InboxMessage::query()->create([
            'team_id' => $connection->team_id,
            'mail_connection_id' => $connection->id,
            'lead_id' => $lead?->id,
            'email_thread_id' => $thread?->id,
            'lead_activity_id' => $activity?->id,
            'external_message_id' => $message->externalMessageId,
            'thread_id' => $message->threadId,
            'in_reply_to' => $message->inReplyTo,
            'from_address' => $message->fromAddress,
            'to_address' => $message->toAddress,
            'subject' => $message->subject,
            'snippet' => $snippet,
            'received_at' => $message->receivedAt,
        ]);
    }

    /**
     * Mark a connection healthy after a successful sync pass.
     */
    public function markSyncSuccess(MailConnection $connection): void
    {
        $connection->forceFill([
            'status' => MailConnectionStatus::Connected,
            'last_sync_at' => now(),
            'last_sync_error' => null,
        ])->save();
    }

    /**
     * Record a sync failure without logging message bodies.
     */
    public function markSyncFailure(MailConnection $connection, string $error): void
    {
        $connection->forceFill([
            'status' => MailConnectionStatus::Error,
            'last_sync_at' => now(),
            'last_sync_error' => Str::limit($error, 240),
        ])->save();
    }

    protected function alreadyImported(MailConnection $connection, string $externalMessageId): bool
    {
        return InboxMessage::query()
            ->where('mail_connection_id', $connection->id)
            ->where('external_message_id', $externalMessageId)
            ->exists();
    }

    protected function matchLead(MailConnection $connection, InboundMailMessage $message): ?Lead
    {
        $addresses = array_filter([
            $this->normalizeEmail($message->fromAddress),
            $this->normalizeEmail($message->toAddress),
        ]);

        foreach ($addresses as $address) {
            if ($address === $this->normalizeEmail($connection->email_address)) {
                continue;
            }

            $lead = Lead::query()
                ->where('team_id', $connection->team_id)
                ->whereRaw('lower(email) = ?', [$address])
                ->first();

            if ($lead !== null) {
                return $lead;
            }
        }

        return null;
    }

    protected function matchThread(MailConnection $connection, Lead $lead, InboundMailMessage $message): ?EmailThread
    {
        $referenceIds = array_values(array_unique(array_filter([
            $message->inReplyTo,
            ...$message->references,
        ])));

        if ($referenceIds !== []) {
            $byReply = EmailThread::query()
                ->where('team_id', $connection->team_id)
                ->where('lead_id', $lead->id)
                ->where(function ($query) use ($referenceIds) {
                    foreach ($referenceIds as $referenceId) {
                        $query->orWhere('last_outbound_message_id', $referenceId)
                            ->orWhere('root_message_id', $referenceId);
                    }
                })
                ->first();

            if ($byReply !== null) {
                return $byReply;
            }
        }

        $normalized = EmailSubjectNormalizer::normalize($message->subject);

        return EmailThread::query()
            ->where('team_id', $connection->team_id)
            ->where('lead_id', $lead->id)
            ->where('subject_normalized', $normalized)
            ->latest('last_outbound_at')
            ->first();
    }

    protected function normalizeEmail(string $address): string
    {
        if (preg_match('/<([^>]+)>/', $address, $matches)) {
            return mb_strtolower(trim($matches[1]));
        }

        return mb_strtolower(trim($address));
    }

    protected function pauseActiveSequences(Lead $lead): void
    {
        SequenceEnrollment::query()
            ->where('team_id', $lead->team_id)
            ->where('lead_id', $lead->id)
            ->where('status', EnrollmentStatus::Active)
            ->each(fn (SequenceEnrollment $enrollment) => $enrollment->stop(__('Prospect replied to your email.')));
    }
}

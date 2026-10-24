<?php

namespace App\Services\Mail;

use App\Models\EmailThread;
use App\Models\Lead;
use Illuminate\Support\Str;

class OutboundThreadRecorder
{
    /**
     * Register an outbound email so replies can be matched to the lead.
     */
    public function record(Lead $lead, string $subject, ?string $messageId = null): EmailThread
    {
        $messageId ??= $this->generateMessageId();
        $normalized = EmailSubjectNormalizer::normalize($subject);

        $thread = EmailThread::query()->firstOrNew([
            'team_id' => $lead->team_id,
            'lead_id' => $lead->id,
            'subject_normalized' => $normalized,
        ]);

        $thread->fill([
            'root_message_id' => $thread->root_message_id ?? $messageId,
            'last_outbound_message_id' => $messageId,
            'last_outbound_at' => now(),
        ])->save();

        return $thread;
    }

    /**
     * Build a Message-ID header value for app-sent mail.
     */
    public function generateMessageId(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        return mb_strtolower('<'.Str::uuid()->toString().'@'.$host.'>');
    }
}

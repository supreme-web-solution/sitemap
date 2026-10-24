<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $mail_connection_id
 * @property int|null $lead_id
 * @property int|null $email_thread_id
 * @property int|null $lead_activity_id
 * @property string $external_message_id
 * @property string|null $thread_id
 * @property string|null $in_reply_to
 * @property string $from_address
 * @property string $to_address
 * @property string $subject
 * @property string $snippet
 * @property Carbon $received_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read MailConnection $mailConnection
 * @property-read Lead|null $lead
 * @property-read EmailThread|null $emailThread
 * @property-read LeadActivity|null $leadActivity
 */
#[Fillable([
    'team_id',
    'mail_connection_id',
    'lead_id',
    'email_thread_id',
    'lead_activity_id',
    'external_message_id',
    'thread_id',
    'in_reply_to',
    'from_address',
    'to_address',
    'subject',
    'snippet',
    'received_at',
])]
class InboxMessage extends Model
{
    /**
     * Get the team the message belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the mailbox that imported the message.
     *
     * @return BelongsTo<MailConnection, $this>
     */
    public function mailConnection(): BelongsTo
    {
        return $this->belongsTo(MailConnection::class);
    }

    /**
     * Get the matched lead, if any.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the outreach thread, if matched.
     *
     * @return BelongsTo<EmailThread, $this>
     */
    public function emailThread(): BelongsTo
    {
        return $this->belongsTo(EmailThread::class);
    }

    /**
     * Get the timeline activity created for this message.
     *
     * @return BelongsTo<LeadActivity, $this>
     */
    public function leadActivity(): BelongsTo
    {
        return $this->belongsTo(LeadActivity::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }
}

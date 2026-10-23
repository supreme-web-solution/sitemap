<?php

namespace App\Models;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $lead_id
 * @property int|null $user_id
 * @property MessageChannel $channel
 * @property string $direction
 * @property MessageStatus $status
 * @property string $body
 * @property string $phone
 * @property string|null $external_id
 * @property string|null $external_conversation_id
 * @property array{name: string, language: string, params: list<string>}|null $template
 * @property string|null $error
 * @property Carbon|null $send_after
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lead $lead
 * @property-read Team $team
 */
#[Fillable(['team_id', 'lead_id', 'user_id', 'channel', 'direction', 'status', 'body', 'phone', 'external_id', 'external_conversation_id', 'template', 'error', 'send_after', 'sent_at'])]
class Message extends Model
{
    public const OUTBOUND = 'out';

    public const INBOUND = 'in';

    /**
     * Get the lead the message was exchanged with.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'status' => MessageStatus::class,
            'template' => 'array',
            'send_after' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}

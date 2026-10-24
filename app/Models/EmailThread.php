<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $lead_id
 * @property string $subject_normalized
 * @property string|null $root_message_id
 * @property string|null $last_outbound_message_id
 * @property Carbon|null $last_outbound_at
 * @property Carbon|null $replied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Lead $lead
 */
#[Fillable(['team_id', 'lead_id', 'subject_normalized', 'root_message_id', 'last_outbound_message_id', 'last_outbound_at', 'replied_at'])]
class EmailThread extends Model
{
    /**
     * Get the team the thread belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the lead this thread is with.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get inbound messages linked to this thread.
     *
     * @return HasMany<InboxMessage, $this>
     */
    public function inboxMessages(): HasMany
    {
        return $this->hasMany(InboxMessage::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_outbound_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }
}

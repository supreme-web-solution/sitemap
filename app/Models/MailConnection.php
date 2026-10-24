<?php

namespace App\Models;

use App\Enums\MailConnectionProvider;
use App\Enums\MailConnectionStatus;
use Database\Factories\MailConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property MailConnectionProvider $provider
 * @property string $email_address
 * @property array<string, mixed>|null $credentials
 * @property MailConnectionStatus $status
 * @property Carbon|null $last_sync_at
 * @property string|null $last_sync_error
 * @property string|null $sync_cursor
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable(['provider', 'email_address', 'credentials', 'status', 'last_sync_at', 'last_sync_error', 'sync_cursor'])]
#[Hidden(['credentials'])]
class MailConnection extends Model
{
    /** @use HasFactory<MailConnectionFactory> */
    use HasFactory;

    /**
     * Whether automatic polling can run for this connection.
     */
    public function syncAvailable(): bool
    {
        return $this->provider->syncAvailable();
    }

    /**
     * Get the team the mailbox belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get messages imported from this mailbox.
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
            'provider' => MailConnectionProvider::class,
            'credentials' => 'encrypted:array',
            'status' => MailConnectionStatus::class,
            'last_sync_at' => 'datetime',
        ];
    }
}

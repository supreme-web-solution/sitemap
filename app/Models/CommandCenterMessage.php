<?php

namespace App\Models;

use App\Enums\CommandCenterMessageSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property string $role
 * @property CommandCenterMessageSource $source
 * @property string $body
 */
#[Fillable(['team_id', 'user_id', 'role', 'source', 'body'])]
class CommandCenterMessage extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (CommandCenterMessage $message): void {
            if ($message->created_at === null) {
                $message->created_at = now();
            }
        });
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => CommandCenterMessageSource::class,
            'created_at' => 'datetime',
        ];
    }
}

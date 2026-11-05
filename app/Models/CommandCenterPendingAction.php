<?php

namespace App\Models;

use App\Enums\CommandCenterPendingActionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property string $action
 * @property string $summary
 * @property array<string, mixed> $payload
 * @property CommandCenterPendingActionStatus $status
 */
#[Fillable(['team_id', 'user_id', 'action', 'summary', 'payload', 'status', 'resolved_at'])]
class CommandCenterPendingAction extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (CommandCenterPendingAction $row): void {
            if ($row->created_at === null) {
                $row->created_at = now();
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
            'payload' => 'array',
            'status' => CommandCenterPendingActionStatus::class,
            'created_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}

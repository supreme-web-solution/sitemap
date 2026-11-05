<?php

namespace App\Models;

use App\Enums\AutomationTrigger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $lead_id
 * @property int|null $user_id
 * @property string $trigger
 * @property string $summary
 * @property list<string> $actions
 * @property-read Team $team
 * @property-read Lead|null $lead
 */
#[Fillable(['team_id', 'lead_id', 'user_id', 'trigger', 'summary', 'actions'])]
class AutomationRun extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (AutomationRun $run): void {
            if ($run->created_at === null) {
                $run->created_at = now();
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
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actions' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function triggerEnum(): AutomationTrigger
    {
        return AutomationTrigger::from($this->trigger);
    }
}

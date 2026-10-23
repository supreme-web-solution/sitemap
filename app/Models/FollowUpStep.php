<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $follow_up_sequence_id
 * @property int $position
 * @property int $delay_days
 * @property string $subject
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['follow_up_sequence_id', 'position', 'delay_days', 'subject', 'body'])]
class FollowUpStep extends Model
{
    /**
     * @return BelongsTo<FollowUpSequence, $this>
     */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(FollowUpSequence::class, 'follow_up_sequence_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'delay_days' => 'integer',
        ];
    }
}

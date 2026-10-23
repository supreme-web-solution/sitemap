<?php

namespace App\Enums;

enum LeadSearchStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Determine if the search has reached a final state.
     */
    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}

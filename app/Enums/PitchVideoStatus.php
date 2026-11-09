<?php

namespace App\Enums;

enum PitchVideoStatus: string
{
    case Draft = 'draft';
    case Generating = 'generating';
    case Ready = 'ready';
    case Failed = 'failed';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Generating => 'Creating video',
            self::Ready => 'Ready',
            self::Failed => 'Failed',
        };
    }
}

<?php

namespace App\Enums;

enum MailConnectionStatus: string
{
    case Pending = 'pending';
    case Connected = 'connected';
    case Error = 'error';

    /**
     * Get the display label for the connection status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending first sync',
            self::Connected => 'Connected',
            self::Error => 'Sync error',
        };
    }
}

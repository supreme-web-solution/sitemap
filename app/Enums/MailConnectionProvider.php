<?php

namespace App\Enums;

enum MailConnectionProvider: string
{
    case Imap = 'imap';
    case GoogleOAuth = 'google_oauth';

    /**
     * Get the display label for the provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::Imap => 'IMAP mailbox',
            self::GoogleOAuth => 'Google Workspace (OAuth)',
        };
    }

    /**
     * Whether the app can sync this provider without extra Composer packages.
     */
    public function syncAvailable(): bool
    {
        return match ($this) {
            self::Imap => (bool) config('inbox.imap_sync_enabled'),
            self::GoogleOAuth => false,
        };
    }
}

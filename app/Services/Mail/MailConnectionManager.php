<?php

namespace App\Services\Mail;

use App\Contracts\Mail\MailConnectionClient;
use App\Enums\MailConnectionProvider;
use App\Models\MailConnection;

class MailConnectionManager
{
    /**
     * Resolve the sync client for a saved connection.
     */
    public function client(MailConnection $connection): MailConnectionClient
    {
        return match ($connection->provider) {
            MailConnectionProvider::Imap => config('inbox.imap_sync_enabled')
                ? app(ImapMailConnectionClient::class)
                : app(StubImapMailConnectionClient::class),
            MailConnectionProvider::GoogleOAuth => app(StubGoogleMailConnectionClient::class),
        };
    }
}

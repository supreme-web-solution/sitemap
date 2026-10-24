<?php

namespace App\Services\Mail;

use App\Contracts\Mail\MailConnectionClient;
use App\Models\MailConnection;
use Illuminate\Support\Carbon;

/**
 * Placeholder IMAP client until an IMAP library is approved for the project.
 */
class StubImapMailConnectionClient implements MailConnectionClient
{
    /**
     * {@inheritDoc}
     */
    public function verify(MailConnection $connection): void
    {
        if (! config('inbox.imap_sync_enabled')) {
            throw new MailConnectionUnavailableException(
                __('Automatic IMAP sync is not enabled on this server yet. Save your mailbox settings and log replies manually from each lead until sync ships.'),
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function fetchSince(MailConnection $connection, ?Carbon $since): array
    {
        if (! config('inbox.imap_sync_enabled')) {
            return [];
        }

        return [];
    }
}

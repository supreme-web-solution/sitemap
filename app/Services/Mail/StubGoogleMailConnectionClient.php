<?php

namespace App\Services\Mail;

use App\Contracts\Mail\MailConnectionClient;
use App\Models\MailConnection;
use Illuminate\Support\Carbon;

/**
 * Placeholder Google OAuth inbox client (Socialite not installed).
 */
class StubGoogleMailConnectionClient implements MailConnectionClient
{
    /**
     * {@inheritDoc}
     */
    public function verify(MailConnection $connection): void
    {
        throw new MailConnectionUnavailableException(
            __('Google inbox connection is not available yet. Use IMAP settings or log replies manually from each lead.'),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function fetchSince(MailConnection $connection, ?Carbon $since): array
    {
        return [];
    }
}

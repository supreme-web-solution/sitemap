<?php

namespace App\Contracts\Mail;

use App\Models\MailConnection;
use App\Services\Mail\InboundMailMessage;
use Illuminate\Support\Carbon;

interface MailConnectionClient
{
    /**
     * Verify credentials without importing messages.
     */
    public function verify(MailConnection $connection): void;

    /**
     * Fetch inbound messages received after the given moment.
     *
     * @return list<InboundMailMessage>
     */
    public function fetchSince(MailConnection $connection, ?Carbon $since): array;
}

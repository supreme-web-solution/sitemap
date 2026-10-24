<?php

namespace Tests\Support;

use App\Contracts\Mail\MailConnectionClient;
use App\Models\MailConnection;
use App\Services\Mail\InboundMailMessage;
use Illuminate\Support\Carbon;

class FakeImapMailConnectionClient implements MailConnectionClient
{
    /**
     * @param  list<InboundMailMessage>  $messages
     */
    public function __construct(public array $messages = []) {}

    /**
     * {@inheritDoc}
     */
    public function verify(MailConnection $connection): void {}

    /**
     * {@inheritDoc}
     */
    public function fetchSince(MailConnection $connection, ?Carbon $since): array
    {
        return $this->messages;
    }
}

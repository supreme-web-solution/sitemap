<?php

namespace App\Services\Mail;

use Illuminate\Support\Carbon;

/**
 * A normalized inbound email fetched from a connected mailbox.
 */
readonly class InboundMailMessage
{
    /**
     * @param  list<string>  $references
     */
    public function __construct(
        public string $externalMessageId,
        public string $fromAddress,
        public string $toAddress,
        public string $subject,
        public string $snippet,
        public Carbon $receivedAt,
        public ?string $threadId = null,
        public ?string $inReplyTo = null,
        public array $references = [],
    ) {}
}

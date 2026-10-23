<?php

namespace App\Services\Messaging;

use RuntimeException;

class MessagingException extends RuntimeException
{
    /**
     * @param  bool  $retryable  Whether trying again later could work (rate limits, outages) rather than never (bad number, rejected key).
     */
    public function __construct(string $message, public readonly bool $retryable = false, public readonly bool $optedOut = false)
    {
        parent::__construct($message);
    }
}

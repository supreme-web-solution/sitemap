<?php

namespace App\Services\Audit;

use RuntimeException;

class NonSuccessHttpResponseException extends RuntimeException
{
    public function __construct(public int $status, string $message)
    {
        parent::__construct($message);
    }
}

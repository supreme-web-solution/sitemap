<?php

namespace App\Services\SiteBuilder;

use App\Services\Ai\AiProviderErrors;
use Throwable;

/**
 * Per-request tally of AI provider failures during one chat edit job.
 */
class SiteEditAiContext
{
    private static int $quotaFailures = 0;

    private static ?string $lastMessage = null;

    public static function reset(): void
    {
        self::$quotaFailures = 0;
        self::$lastMessage = null;
    }

    public static function record(Throwable|string|null $error): void
    {
        if (! AiProviderErrors::isQuotaOrAuth($error)) {
            return;
        }

        self::$quotaFailures++;
        self::$lastMessage = $error instanceof Throwable ? $error->getMessage() : (string) $error;
    }

    public static function hadQuotaFailure(): bool
    {
        return self::$quotaFailures > 0;
    }

    public static function lastMessage(): ?string
    {
        return self::$lastMessage;
    }
}

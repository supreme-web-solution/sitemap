<?php

namespace App\Services\Ai;

use Throwable;

/**
 * Detect Anthropic / Laravel AI billing and quota failures and map them to user-facing copy.
 */
class AiProviderErrors
{
    public static function isQuotaOrAuth(Throwable|string|null $error): bool
    {
        if ($error === null) {
            return false;
        }

        $message = strtolower($error instanceof Throwable ? $error->getMessage() : $error);

        if ($message === '') {
            return false;
        }

        foreach ([
            'insufficient credit',
            'insufficient quota',
            'credit balance',
            'quota',
            'billing',
            'payment required',
            'exceeded your current',
            'rate limit',
            'too many requests',
            '401',
            '402',
            '429',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function siteEditUnavailableMessage(): string
    {
        return __('This service is not available right now.');
    }

    public static function siteEditPartialUnavailableMessage(): string
    {
        return __('This service is not available right now.');
    }

    public static function serviceImagesNeedImageApiMessage(): string
    {
        return __('This service is not available right now.');
    }
}

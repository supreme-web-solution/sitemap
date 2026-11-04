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
        return __('AI editing is temporarily unavailable (Anthropic credits or quota). Add credits in your Anthropic console, then use Try again. Layout-only fixes may still work when they match built-in presets.');
    }

    public static function siteEditPartialUnavailableMessage(): string
    {
        return __('Some parts of this request need AI (design CSS, copy planning, or generated photos) but Anthropic returned a credits or quota error. Top up Anthropic or configure OpenRouter/OpenAI for photos, then try again.');
    }

    public static function serviceImagesNeedImageApiMessage(): string
    {
        return __('Generating images for each service needs an image API (OpenRouter or OpenAI in your .env). Anthropic text credits alone are not enough for that step.');
    }
}

<?php

namespace App\Services\SiteBuilder;

use Illuminate\Http\Client\Response;

/**
 * User-facing copy when lead/site photo generation fails or is not configured.
 */
class ImageGenerationMessages
{
    public static function notConfiguredMessage(): string
    {
        return __('AI photo generation is not set up on this site yet. Upload a hero image in Photos, or try again later.');
    }

    public static function unavailableMessage(): string
    {
        return __('AI photo generation is not available right now. Try again later, or upload a hero photo in Photos.');
    }

    public static function messageForFailedResponse(Response $response): string
    {
        $status = $response->status();
        $apiMessage = strtolower((string) ($response->json('error.message') ?? ''));

        if ($status === 429 || str_contains($apiMessage, 'credit') || str_contains($apiMessage, 'quota')) {
            return self::unavailableMessage();
        }

        if (in_array($status, [401, 403], true)) {
            return self::unavailableMessage();
        }

        if ($status >= 500) {
            return __('The photo service is temporarily down. Try again in a few minutes, or upload a photo in Photos.');
        }

        return self::unavailableMessage();
    }
}

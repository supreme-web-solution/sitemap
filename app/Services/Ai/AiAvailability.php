<?php

namespace App\Services\Ai;

/**
 * Shared checks for Anthropic / Laravel AI configuration in this app.
 */
class AiAvailability
{
    public static function anthropicConfigured(): bool
    {
        return filled(config('services.anthropic.key'))
            || filled(config('ai.providers.anthropic.key'));
    }

    public static function anthropicModel(): string
    {
        $model = config('services.anthropic.model');

        if (is_string($model) && $model !== '') {
            return $model;
        }

        return 'claude-sonnet-4-6';
    }

    public static function anthropicFastModel(): string
    {
        $model = config('services.anthropic.fast_model');

        if (is_string($model) && $model !== '') {
            return $model;
        }

        return 'claude-haiku-4-5';
    }
}

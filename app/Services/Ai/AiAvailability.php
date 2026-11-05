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

    public static function openaiConfigured(): bool
    {
        return filled(config('services.openai.key'))
            || filled(config('ai.providers.openai.key'));
    }

    /**
     * Model from COMMAND_CENTER_OPENAI_MODEL, then OPENAI_MODEL.
     */
    public static function openaiModel(): string
    {
        $model = config('command_center.openai_model');

        if (is_string($model) && trim($model) !== '') {
            return trim($model);
        }

        return 'gpt-4o-mini';
    }
}

<?php

namespace App\Services\SiteBuilder;

use App\Data\SiteImageGenerationPlan;

/**
 * Generates lead/site photos via OpenAI or OpenRouter (configurable).
 */
class LeadPhotoGenerator
{
    public function __construct(
        protected OpenAiImageGenerator $openAi,
        protected OpenRouterImageGenerator $openRouter,
    ) {}

    /**
     * @return array{path: string, url: string}
     */
    public function generate(SiteImageGenerationPlan $plan, int $teamId, int $leadId): array
    {
        return match ($this->activeDriver()) {
            'openrouter' => $this->openRouter->generate($plan, $teamId, $leadId),
            default => $this->openAi->generate($plan, $teamId, $leadId),
        };
    }

    public function configured(): bool
    {
        return match ($this->activeDriver()) {
            'openrouter' => filled(config('services.openrouter.key')),
            default => filled(config('services.openai.key')),
        };
    }

    public function activeDriver(): string
    {
        return self::resolveDriver();
    }

    public static function resolveDriver(): string
    {
        $preference = (string) config('sites.image_driver', 'auto');

        if ($preference === 'openrouter') {
            return 'openrouter';
        }

        if ($preference === 'openai') {
            return 'openai';
        }

        if (filled(config('services.openai.key'))) {
            return 'openai';
        }

        if (filled(config('services.openrouter.key'))) {
            return 'openrouter';
        }

        return 'openai';
    }

    public static function unavailableMessage(): string
    {
        return ImageGenerationMessages::unavailableMessage();
    }

    public static function notConfiguredMessage(): string
    {
        return ImageGenerationMessages::notConfiguredMessage();
    }
}

<?php

namespace App\Ai\Concerns;

use App\Services\Ai\AiAvailability;
use Laravel\Ai\Enums\Lab;

/**
 * OpenAI settings for the Command Center agent.
 *
 * Provider, model, and timeout are methods because Laravel AI reads class
 * attributes only — attributes on this trait are ignored, and a missing
 * provider() falls through to AI_DEFAULT_PROVIDER (Anthropic in this app).
 */
trait UsesAppOpenAi
{
    public function provider(): Lab
    {
        return Lab::OpenAI;
    }

    public function model(): string
    {
        return AiAvailability::openaiModel();
    }

    public function timeout(): int
    {
        return 120;
    }

    public function maxTokens(): int
    {
        return 4096;
    }
}

<?php

namespace App\Ai\Concerns;

use App\Services\Ai\AiAvailability;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Enums\Lab;

/**
 * Default Anthropic provider settings for first-party agents in this app.
 */
#[Provider(Lab::Anthropic)]
#[MaxTokens(4096)]
#[Timeout(120)]
trait UsesAppAnthropic
{
    public function model(): ?string
    {
        return AiAvailability::anthropicModel();
    }
}

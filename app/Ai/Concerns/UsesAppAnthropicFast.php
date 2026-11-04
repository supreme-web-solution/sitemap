<?php

namespace App\Ai\Concerns;

use App\Services\Ai\AiAvailability;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Enums\Lab;

/**
 * Cheaper Anthropic model for site edit routing, scoped CSS, and reviews.
 */
#[Provider(Lab::Anthropic)]
#[MaxTokens(4096)]
#[Timeout(90)]
trait UsesAppAnthropicFast
{
    public function model(): ?string
    {
        return AiAvailability::anthropicFastModel();
    }
}

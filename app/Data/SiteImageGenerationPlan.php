<?php

namespace App\Data;

use App\Services\SiteBuilder\SiteImageAspectRatio;

/**
 * Where and how to generate one lead/site photo.
 */
final readonly class SiteImageGenerationPlan
{
    public function __construct(
        public string $prompt,
        public string $aspectRatio,
        public string $placement,
        public string $reasoning,
        public bool $fromLlm = false,
    ) {}

    public function openAiSize(): string
    {
        return SiteImageAspectRatio::openAiSizeForRatio($this->aspectRatio);
    }
}

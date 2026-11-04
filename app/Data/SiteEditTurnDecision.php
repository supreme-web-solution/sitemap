<?php

namespace App\Data;

final readonly class SiteEditTurnDecision
{
    public function __construct(
        public string $message,
        public ?string $applyInstruction,
        public bool $generateHeroImage,
        public ?string $imageHint,
        public bool $fromLlm,
        public bool $applyAttachedImage = false,
        public string $attachedImagePlacement = 'hero',
    ) {}

    public function shouldApplyEdit(): bool
    {
        return filled($this->applyInstruction);
    }

    public function shouldGenerateHeroImage(): bool
    {
        return $this->generateHeroImage;
    }

    public function shouldApplyAttachedImage(): bool
    {
        return $this->applyAttachedImage;
    }

    public function isReplyOnly(): bool
    {
        return ! $this->shouldApplyEdit()
            && ! $this->shouldGenerateHeroImage()
            && ! $this->shouldApplyAttachedImage();
    }
}

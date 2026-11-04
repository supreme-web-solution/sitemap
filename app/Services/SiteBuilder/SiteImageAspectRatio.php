<?php

namespace App\Services\SiteBuilder;

/**
 * Normalizes aspect ratios for OpenRouter and maps to OpenAI image sizes.
 */
class SiteImageAspectRatio
{
    /** @var list<string> */
    public const OPENROUTER = [
        '1:1', '2:3', '3:2', '3:4', '4:3', '4:5', '5:4', '9:16', '16:9', '21:9',
    ];

    public static function normalize(?string $ratio, string $fallback = '16:9'): string
    {
        $ratio = trim((string) $ratio);

        if ($ratio === '') {
            return $fallback;
        }

        if (in_array($ratio, self::OPENROUTER, true)) {
            return $ratio;
        }

        $normalized = str_replace(' ', '', strtolower($ratio));

        foreach (self::OPENROUTER as $allowed) {
            if ($normalized === str_replace(' ', '', $allowed)) {
                return $allowed;
            }
        }

        return $fallback;
    }

    public static function openAiSizeForRatio(string $aspectRatio): string
    {
        return match (self::normalize($aspectRatio)) {
            '1:1' => '1024x1024',
            '9:16', '2:3', '3:4', '4:5' => '1024x1536',
            '16:9', '21:9', '3:2', '4:3', '5:4' => '1536x1024',
            default => '1536x1024',
        };
    }

    /**
     * Contextual default when the LLM planner is unavailable.
     */
    public static function defaultForHeroVariant(string $variant): string
    {
        return match ($variant) {
            'split' => '3:4',
            'card' => '4:5',
            'photo-top' => '21:9',
            'overlay', 'centered', 'minimal', 'classic' => '16:9',
            default => '16:9',
        };
    }

    public static function defaultForPhotoIndex(int $index, string $heroVariant): string
    {
        if ($index === 0) {
            return self::defaultForHeroVariant($heroVariant);
        }

        return '4:3';
    }
}

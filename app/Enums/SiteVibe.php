<?php

namespace App\Enums;

enum SiteVibe: string
{
    case Modern = 'modern';
    case Warm = 'warm';
    case Bold = 'bold';

    /**
     * Get the display label for the vibe.
     */
    public function label(): string
    {
        return match ($this) {
            self::Modern => 'Modern — clean, bright and professional',
            self::Warm => 'Warm — friendly, soft tones and inviting',
            self::Bold => 'Bold — dark, high-contrast and premium',
        };
    }

    /**
     * Get the template designed for this vibe.
     */
    public function template(): SiteTemplate
    {
        return match ($this) {
            self::Modern => SiteTemplate::ModernClean,
            self::Warm => SiteTemplate::WarmStudio,
            self::Bold => SiteTemplate::EditorialDark,
        };
    }

    /**
     * Get all vibes as value/label pairs.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $vibe) => ['value' => $vibe->value, 'label' => $vibe->label()],
            self::cases(),
        );
    }
}

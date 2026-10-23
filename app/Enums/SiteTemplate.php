<?php

namespace App\Enums;

enum SiteTemplate: string
{
    case ModernClean = 'modern-clean';
    case WarmStudio = 'warm-studio';
    case EditorialDark = 'editorial-dark';

    /**
     * Get the display label for the template.
     */
    public function label(): string
    {
        return match ($this) {
            self::ModernClean => 'Modern Clean Pro',
            self::WarmStudio => 'Warm Studio',
            self::EditorialDark => 'Editorial Dark',
        };
    }

    /**
     * Get the default accent colour for the template.
     */
    public function defaultAccent(): string
    {
        return match ($this) {
            self::ModernClean => '#2563eb',
            self::WarmStudio => '#c2410c',
            self::EditorialDark => '#f59e0b',
        };
    }

    /**
     * Get the approved accent palette for the template.
     *
     * @return list<string>
     */
    public function palette(): array
    {
        return match ($this) {
            self::ModernClean => ['#2563eb', '#0d9488', '#0891b2', '#16a34a', '#ca8a04', '#9a3412', '#0284c7'],
            self::WarmStudio => ['#c2410c', '#b45309', '#be123c', '#a16207', '#15803d', '#7c2d12'],
            self::EditorialDark => ['#f59e0b', '#e11d48', '#dc2626', '#84cc16', '#b45309', '#38bdf8'],
        };
    }

    /**
     * Get all templates as value/label pairs.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $template) => ['value' => $template->value, 'label' => $template->label()],
            self::cases(),
        );
    }

    /**
     * Get the owner-facing vibe this template was designed for.
     */
    public function vibe(): SiteVibe
    {
        return match ($this) {
            self::ModernClean => SiteVibe::Modern,
            self::WarmStudio => SiteVibe::Warm,
            self::EditorialDark => SiteVibe::Bold,
        };
    }
}

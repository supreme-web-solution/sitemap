<?php

namespace App\Enums;

enum SiteDesignStyle: string
{
    case Modern = 'modern';
    case Warm = 'warm';
    case Bold = 'bold';
    case Minimal = 'minimal';
    case Elegant = 'elegant';

    /**
     * Get the display label for the style.
     */
    public function label(): string
    {
        return match ($this) {
            self::Modern => 'Modern',
            self::Warm => 'Warm',
            self::Bold => 'Bold',
            self::Minimal => 'Minimal',
            self::Elegant => 'Elegant',
        };
    }

    /**
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $style) => ['value' => $style->value, 'label' => $style->label()],
            self::cases(),
        );
    }
}

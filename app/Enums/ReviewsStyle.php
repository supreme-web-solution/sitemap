<?php

namespace App\Enums;

enum ReviewsStyle: string
{
    case Carousel = 'carousel';
    case Grid = 'grid';
    case Spotlight = 'spotlight';
    case Marquee = 'marquee';

    /**
     * The fewest reviews the style needs to look right.
     */
    public function minimumReviews(): int
    {
        return match ($this) {
            self::Marquee => 5,
            default => 3,
        };
    }

    /**
     * Get the display label for the style.
     */
    public function label(): string
    {
        return match ($this) {
            self::Carousel => 'Carousel (swipe through cards)',
            self::Grid => 'Wall of reviews',
            self::Spotlight => 'Spotlight (one big quote)',
            self::Marquee => 'Scrolling ticker',
        };
    }

    /**
     * Get all styles as value/label pairs.
     *
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

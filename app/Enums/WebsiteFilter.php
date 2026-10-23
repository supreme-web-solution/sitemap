<?php

namespace App\Enums;

enum WebsiteFilter: string
{
    case WithoutWebsite = 'withoutWebsite';
    case WithWebsite = 'withWebsite';
    case AllPlaces = 'allPlaces';

    /**
     * Get the display label for the filter.
     */
    public function label(): string
    {
        return match ($this) {
            self::WithoutWebsite => 'No website',
            self::WithWebsite => 'Has website',
            self::AllPlaces => 'All businesses',
        };
    }

    /**
     * Get all filters as value/label pairs.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $filter) => ['value' => $filter->value, 'label' => $filter->label()],
            self::cases(),
        );
    }
}

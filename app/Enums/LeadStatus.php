<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case SiteGenerated = 'site_generated';
    case Contacted = 'contacted';
    case Interested = 'interested';
    case Won = 'won';
    case Lost = 'lost';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::SiteGenerated => 'Site generated',
            self::Contacted => 'Contacted',
            self::Interested => 'Interested',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    /**
     * Get all statuses as value/label pairs.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status) => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}

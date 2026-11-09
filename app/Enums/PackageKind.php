<?php

namespace App\Enums;

enum PackageKind: string
{
    case OneTime = 'one_time';
    case Hosting = 'hosting';
    case Maintenance = 'maintenance';
    case Service = 'service';

    /**
     * Get the display label for the kind.
     */
    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'One-time',
            self::Hosting => 'Hosting',
            self::Maintenance => 'Maintenance',
            self::Service => 'Service',
        };
    }

    /**
     * Whether this kind is usually billed on a schedule.
     */
    public function prefersRecurring(): bool
    {
        return match ($this) {
            self::Hosting, self::Maintenance => true,
            self::OneTime, self::Service => false,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $kind) => ['value' => $kind->value, 'label' => $kind->label()],
            self::cases(),
        );
    }
}

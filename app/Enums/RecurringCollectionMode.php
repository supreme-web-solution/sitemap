<?php

namespace App\Enums;

enum RecurringCollectionMode: string
{
    case Invoice = 'invoice';
    case AutoCharge = 'auto_charge';

    /**
     * Get the display label for the collection mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Send a pay link each cycle',
            self::AutoCharge => 'Charge saved card automatically',
        };
    }

    /**
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $mode) => ['value' => $mode->value, 'label' => $mode->label()],
            self::cases(),
        );
    }
}

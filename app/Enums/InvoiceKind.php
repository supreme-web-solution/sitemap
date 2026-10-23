<?php

namespace App\Enums;

enum InvoiceKind: string
{
    case Standard = 'standard';
    case Deposit = 'deposit';
    case Milestone = 'milestone';

    /**
     * Get the display label for the kind.
     */
    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Deposit => 'Deposit',
            self::Milestone => 'Milestone',
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

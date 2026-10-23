<?php

namespace App\Enums;

enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    /**
     * Get the number of months between invoices.
     */
    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Yearly => 12,
        };
    }

    /**
     * Get the display label for the interval.
     */
    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Every month',
            self::Quarterly => 'Every 3 months',
            self::Yearly => 'Every year',
        };
    }
}

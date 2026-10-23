<?php

namespace App\Enums;

enum LostReason: string
{
    case NoResponse = 'no_response';
    case TooExpensive = 'too_expensive';
    case NoBudget = 'no_budget';
    case Competitor = 'competitor';
    case Timing = 'timing';
    case NotAFit = 'not_a_fit';
    case Other = 'other';

    /**
     * Get the display label for the reason.
     */
    public function label(): string
    {
        return match ($this) {
            self::NoResponse => 'No response',
            self::TooExpensive => 'Too expensive',
            self::NoBudget => 'No budget',
            self::Competitor => 'Went with a competitor',
            self::Timing => 'Bad timing',
            self::NotAFit => 'Not a fit',
            self::Other => 'Other',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $reason) => ['value' => $reason->value, 'label' => $reason->label()],
            self::cases(),
        );
    }
}

<?php

namespace App\Enums;

enum CommandCenterMode: string
{
    case Assist = 'assist';
    case Autopilot = 'autopilot';

    public function label(): string
    {
        return match ($this) {
            self::Assist => 'Assist',
            self::Autopilot => 'Autopilot',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $mode) => [
            'value' => $mode->value,
            'label' => $mode->label(),
        ], self::cases());
    }
}

<?php

namespace App\Enums;

enum SiteSection: string
{
    case Services = 'services';
    case About = 'about';
    case Process = 'process';
    case Why = 'why';
    case Reviews = 'reviews';
    case Gallery = 'gallery';
    case Areas = 'areas';
    case Hours = 'hours';
    case Faq = 'faq';
    case Contact = 'contact';

    /**
     * Get the display label for the section.
     */
    public function label(): string
    {
        return match ($this) {
            self::Services => 'Services',
            self::About => 'About',
            self::Process => 'How it works',
            self::Why => 'Why choose us',
            self::Reviews => 'Customer reviews',
            self::Gallery => 'Photo gallery',
            self::Areas => 'Areas we serve',
            self::Hours => 'Hours & map',
            self::Faq => 'FAQ',
            self::Contact => 'Contact',
        };
    }

    /**
     * Get the default section order.
     *
     * @return list<string>
     */
    public static function defaultOrder(): array
    {
        return array_map(fn (self $section) => $section->value, self::cases());
    }

    /**
     * Get all sections as value/label pairs.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $section) => ['value' => $section->value, 'label' => $section->label()],
            self::cases(),
        );
    }
}

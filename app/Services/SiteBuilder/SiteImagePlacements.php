<?php

namespace App\Services\SiteBuilder;

/**
 * Page-specific photos stored on site content so one page can change without the others.
 */
class SiteImagePlacements
{
    /**
     * @var list<string>
     */
    public const KEYS = [
        'home_hero',
        'about_hero',
        'about_portrait',
        'services_hero',
        'service_hero',
        'gallery_hero',
        'contact_hero',
        'blog_hero',
        'post_hero',
        'landing_hero',
        'location_hero',
    ];

    public static function is(string $slot): bool
    {
        return in_array($slot, self::KEYS, true);
    }

    public static function forPageType(string $type): string
    {
        $key = $type === 'home' ? 'home_hero' : $type.'_hero';

        return self::is($key) ? $key : 'home_hero';
    }
}

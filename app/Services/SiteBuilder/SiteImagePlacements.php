<?php

namespace App\Services\SiteBuilder;

/**
 * Page-specific photos stored on site content so one page can change without the others.
 */
class SiteImagePlacements
{
    /**
     * Home hero is the only slot that may store a short looping background video.
     */
    public const VIDEO_SLOT = 'home_hero';

    public const VIDEO_MAX_KB = 2048;

    /**
     * @var list<string>
     */
    public const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov'];

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
        'cta_band',
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

    public static function allowsVideo(string $slot): bool
    {
        return $slot === self::VIDEO_SLOT;
    }

    public static function isVideoPath(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, self::VIDEO_EXTENSIONS, true);
    }
}

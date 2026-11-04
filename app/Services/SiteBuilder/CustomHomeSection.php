<?php

namespace App\Services\SiteBuilder;

use Illuminate\Support\Str;

/**
 * User-authored homepage blocks (not in the fixed SiteSection enum).
 *
 * Section order uses keys like custom:bright-offer
 */
final class CustomHomeSection
{
    public const PREFIX = 'custom:';

    public static function isKey(string $key): bool
    {
        return str_starts_with($key, self::PREFIX)
            && preg_match('/^custom:[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/', $key) === 1;
    }

    public static function idFromKey(string $key): string
    {
        return Str::after($key, self::PREFIX);
    }

    public static function keyFromId(string $id): string
    {
        return self::PREFIX.$id;
    }

    public static function newId(string $hint = 'block'): string
    {
        $slug = Str::slug($hint);

        if ($slug === '') {
            $slug = 'block';
        }

        return Str::limit($slug, 48, '').'-'.Str::lower(Str::random(4));
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public static function payload(array $content, string $sectionKey): ?array
    {
        if (! self::isKey($sectionKey)) {
            return null;
        }

        $id = self::idFromKey($sectionKey);
        $block = $content['custom_sections'][$id] ?? null;

        return is_array($block) ? $block : null;
    }
}

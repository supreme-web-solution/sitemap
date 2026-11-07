<?php

namespace App\Services\SiteBuilder;

use Illuminate\Support\Arr;

/**
 * Optional per-field look overrides (colour, size, weight) for editable site copy.
 */
class SiteTextStyles
{
    public const SIZES = ['sm', 'md', 'lg'];

    public const WEIGHTS = ['normal', 'bold'];

    /**
     * Paths that may receive a text style override.
     */
    public const PATH_PATTERN = '(?:headline|tagline|cta_label|about|copy\.[a-z_]+|services\.\d+\.(?:title|description|body)|faqs\.\d+\.(?:question|answer)|process\.\d+\.(?:title|description)|why\.\d+\.(?:title|description))';

    /**
     * @return array{color?: string, size?: string, weight?: string}|null
     */
    public static function forPath(array $content, string $path): ?array
    {
        $style = Arr::get($content, 'text_styles.'.$path);

        if (! is_array($style)) {
            return null;
        }

        $clean = self::cleanLeaf($style);

        return $clean === [] ? null : $clean;
    }

    /**
     * Build an inline style attribute value for a content path.
     */
    public static function inline(array $content, string $path): string
    {
        $style = self::forPath($content, $path);

        if ($style === null) {
            return '';
        }

        $parts = [];

        if (isset($style['color'])) {
            $parts[] = 'color:'.$style['color'];
        }

        if (($style['size'] ?? null) === 'sm') {
            $parts[] = 'font-size:0.92rem';
        } elseif (($style['size'] ?? null) === 'lg') {
            $parts[] = 'font-size:1.2rem';
        }

        if (($style['weight'] ?? null) === 'bold') {
            $parts[] = 'font-weight:700';
        } elseif (($style['weight'] ?? null) === 'normal') {
            $parts[] = 'font-weight:400';
        }

        return implode(';', $parts);
    }

    /**
     * Keep only valid colour / size / weight leaves from a nested text_styles tree.
     *
     * @param  array<string, mixed>  $styles
     * @return array<string, mixed>|null
     */
    public static function clean(array $styles): ?array
    {
        $clean = self::cleanNode($styles);

        return $clean === [] ? null : $clean;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    protected static function cleanNode(array $node): array
    {
        if (array_key_exists('color', $node) || array_key_exists('size', $node) || array_key_exists('weight', $node)) {
            return self::cleanLeaf($node);
        }

        $out = [];

        foreach ($node as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (is_string($key) && preg_match('/^[a-z_][a-z0-9_]*$/', $key) !== 1) {
                continue;
            }

            if (! is_string($key) && ! is_int($key)) {
                continue;
            }

            $cleaned = self::cleanNode($value);

            if ($cleaned !== []) {
                $out[$key] = $cleaned;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $leaf
     * @return array{color?: string, size?: string, weight?: string}
     */
    protected static function cleanLeaf(array $leaf): array
    {
        $out = [];
        $color = $leaf['color'] ?? null;

        if (is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1) {
            $out['color'] = strtolower($color);
        }

        $size = $leaf['size'] ?? null;

        if (is_string($size) && in_array($size, self::SIZES, true)) {
            $out['size'] = $size;
        }

        $weight = $leaf['weight'] ?? null;

        if (is_string($weight) && in_array($weight, self::WEIGHTS, true)) {
            $out['weight'] = $weight;
        }

        return $out;
    }
}

<?php

namespace App\Services\SiteBuilder;

/**
 * Deterministic SVG placeholders so every service card has an image slot before generation.
 */
class ServiceCardPlaceholder
{
    /**
     * @var list<array{0: string, 1: string}>
     */
    protected const PALETTES = [
        ['#1e3a5f', '#3d7ea6'],
        ['#3f2e1f', '#c4a574'],
        ['#1f3d2b', '#6a9a78'],
        ['#3b1f2b', '#c47a8a'],
        ['#243044', '#7a93ac'],
        ['#4a3728', '#d4a373'],
        ['#2c3e50', '#5d8aa8'],
        ['#3e2723', '#a1887f'],
    ];

    public static function url(string $title, int $index = 0): string
    {
        $palette = self::PALETTES[$index % count(self::PALETTES)];
        $initial = self::initial($title);
        $label = htmlspecialchars(self::shortLabel($title), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 400" preserveAspectRatio="xMidYMid slice" role="img" aria-hidden="true">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{$palette[0]}"/>
      <stop offset="1" stop-color="{$palette[1]}"/>
    </linearGradient>
  </defs>
  <rect width="640" height="400" fill="url(#g)"/>
  <circle cx="520" cy="70" r="90" fill="rgba(255,255,255,.08)"/>
  <circle cx="80" cy="340" r="120" fill="rgba(0,0,0,.12)"/>
  <text x="48" y="168" fill="rgba(255,255,255,.92)" font-family="Georgia, serif" font-size="92" font-weight="700">{$initial}</text>
  <text x="52" y="230" fill="rgba(255,255,255,.78)" font-family="system-ui, sans-serif" font-size="22">{$label}</text>
</svg>
SVG;

        return 'data:image/svg+xml;charset=UTF-8,'.rawurlencode($svg);
    }

    protected static function initial(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            return 'S';
        }

        return mb_strtoupper(mb_substr($title, 0, 1));
    }

    protected static function shortLabel(string $title): string
    {
        $title = trim($title);

        if (mb_strlen($title) <= 28) {
            return $title;
        }

        return mb_substr($title, 0, 27).'…';
    }
}

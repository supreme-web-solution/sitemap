<?php

namespace App\Services\SiteBuilder;

/**
 * Inline images used when a template photo or logo fails to load.
 */
class SiteImageFallback
{
    public static function photo(string $accent): string
    {
        $accent = self::accent($accent);

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 400" preserveAspectRatio="xMidYMid slice" role="img" aria-hidden="true">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#1e293b"/>
      <stop offset="1" stop-color="{$accent}"/>
    </linearGradient>
  </defs>
  <rect width="640" height="400" fill="url(#g)"/>
  <circle cx="520" cy="64" r="88" fill="rgba(255,255,255,.08)"/>
  <circle cx="78" cy="348" r="110" fill="rgba(0,0,0,.14)"/>
  <g fill="none" stroke="rgba(255,255,255,.88)" stroke-width="8" stroke-linejoin="round" stroke-linecap="round">
    <rect x="220" y="118" width="200" height="148" rx="16"/>
    <path d="M236 242l52-52 36 36 24-28 56 58"/>
  </g>
  <circle cx="278" cy="168" r="14" fill="rgba(255,255,255,.88)"/>
</svg>
SVG;

        return self::uri($svg);
    }

    public static function mark(string $name, string $accent): string
    {
        $accent = self::accent($accent);
        $initial = htmlspecialchars(self::initial($name), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" role="img" aria-hidden="true">
  <rect width="80" height="80" rx="14" fill="{$accent}"/>
  <text x="40" y="52" text-anchor="middle" fill="#ffffff" font-family="Georgia, serif" font-size="36" font-weight="700">{$initial}</text>
</svg>
SVG;

        return self::uri($svg);
    }

    protected static function accent(string $accent): string
    {
        $accent = trim($accent);

        if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $accent) === 1) {
            return $accent;
        }

        return '#1e3a5f';
    }

    protected static function initial(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '•';
        }

        return mb_strtoupper(mb_substr($name, 0, 1));
    }

    protected static function uri(string $svg): string
    {
        return 'data:image/svg+xml;charset=UTF-8,'.rawurlencode($svg);
    }
}

<?php

namespace App\Services\Audit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;

class BrandExtractor
{
    public function __construct(protected SafeHttpFetcher $fetcher, protected WebsiteAuditor $auditor) {}

    /**
     * Pull a brand colour and logo from an existing website.
     *
     * @return array{accent: string|null, logo: string|null, url: string}
     *
     * @throws UnsafeUrlException
     * @throws ConnectionException
     */
    public function extract(string $website): array
    {
        $url = $this->auditor->normalize($website);
        $page = $this->fetcher->fetch($url);
        $html = $page['body'];

        return [
            'url' => $page['url'],
            'accent' => $this->accent($html),
            'logo' => $this->logo($html, $page['url']),
        ];
    }

    protected function accent(string $html): ?string
    {
        $patterns = [
            '/<meta[^>]+name=["\']theme-color["\'][^>]+content=["\']\s*(#[0-9a-fA-F]{3,8})\s*["\']/i',
            '/<meta[^>]+content=["\']\s*(#[0-9a-fA-F]{3,8})\s*["\'][^>]+name=["\']theme-color["\']/i',
            '/--(?:accent|primary|brand(?:-color)?)[^:]*:\s*(#[0-9a-fA-F]{3,8})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $match) === 1) {
                return $this->normalizeHex($match[1]);
            }
        }

        return null;
    }

    protected function logo(string $html, string $pageUrl): ?string
    {
        $patterns = [
            '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i',
            '/<link[^>]+rel=["\'](?:apple-touch-icon|icon)["\'][^>]+href=["\']([^"\']+)["\']/i',
            '/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\'](?:apple-touch-icon|icon)["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $match) === 1) {
                return $this->absoluteUrl($pageUrl, html_entity_decode($match[1]));
            }
        }

        if (preg_match_all('/<img\b[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches) === 0) {
            return null;
        }

        foreach ($matches[0] as $index => $tag) {
            $src = html_entity_decode($matches[1][$index]);

            if (str_starts_with($src, 'data:') || $src === '') {
                continue;
            }

            if (preg_match('/logo|brand|mark/i', $tag.$src) === 1) {
                return $this->absoluteUrl($pageUrl, $src);
            }
        }

        foreach ($matches[1] as $src) {
            $src = html_entity_decode($src);

            if (! str_starts_with($src, 'data:') && $src !== '') {
                return $this->absoluteUrl($pageUrl, $src);
            }
        }

        return null;
    }

    protected function normalizeHex(string $hex): string
    {
        $hex = strtolower($hex);

        if (strlen($hex) === 4) {
            return '#'.$hex[1].$hex[1].$hex[2].$hex[2].$hex[3].$hex[3];
        }

        return Str::limit($hex, 7, '');
    }

    protected function absoluteUrl(string $base, string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (str_starts_with($href, '//')) {
            return ($parts['scheme'] ?? 'https').':'.$href;
        }

        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        return $origin.'/'.$href;
    }
}

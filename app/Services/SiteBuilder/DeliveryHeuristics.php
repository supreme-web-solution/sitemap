<?php

namespace App\Services\SiteBuilder;

class DeliveryHeuristics
{
    /**
     * Lightweight accessibility and weight checks on HTML we already rendered.
     *
     * @return list<array{key: string, label: string, status: string, detail: string}>
     */
    public function inspect(string $html): array
    {
        return [
            $this->language($html),
            $this->viewport($html),
            $this->imageAlt($html),
            $this->imageWeight($html),
            $this->documentSize($html),
            $this->scriptCount($html),
            $this->fontLoading($html),
            $this->heroImageHint($html),
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    protected function language(string $html): array
    {
        $hasLang = preg_match('/<html[^>]+lang=["\'][a-zA-Z-]+["\']/', $html) === 1;

        return $this->result(
            'lang',
            'Document language',
            $hasLang ? 'pass' : 'warn',
            $hasLang ? 'The page declares a language.' : 'The HTML is missing a lang attribute.',
        );
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    protected function viewport(string $html): array
    {
        $hasViewport = preg_match('/<meta[^>]+name=["\']viewport["\']/', $html) === 1;

        return $this->result(
            'viewport',
            'Mobile viewport',
            $hasViewport ? 'pass' : 'warn',
            $hasViewport ? 'A mobile viewport is set.' : 'Add a viewport meta tag so the site fits phones.',
        );
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    protected function imageAlt(string $html): array
    {
        preg_match_all('/<img\b[^>]*>/i', $html, $tags);
        $missing = 0;

        foreach ($tags[0] as $tag) {
            if (! preg_match('/\balt\s*=\s*["\'][^"\']+["\']/', $tag)) {
                $missing++;
            }
        }

        return $this->result(
            'alt',
            'Image descriptions',
            $missing === 0 ? 'pass' : 'warn',
            $missing === 0
                ? 'Images have alternative text.'
                : $missing.' image'.($missing === 1 ? '' : 's').' are missing a useful alt attribute.',
        );
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    protected function imageWeight(string $html): array
    {
        preg_match_all('/src=["\'](data:image\/[^"\']+)["\']/i', $html, $matches);
        $heavy = 0;

        foreach ($matches[1] as $src) {
            if (strlen($src) > 80_000) {
                $heavy++;
            }
        }

        return $this->result(
            'image-weight',
            'Image weight',
            $heavy === 0 ? 'pass' : 'warn',
            $heavy === 0
                ? 'No oversized inline images found.'
                : $heavy.' inline image'.($heavy === 1 ? '' : 's').' look heavy. Use compressed files instead of embedding them.',
        );
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    protected function documentSize(string $html): array
    {
        $bytes = strlen($html);
        $kb = (int) round($bytes / 1024);

        $status = match (true) {
            $bytes <= 120_000 => 'pass',
            $bytes <= 250_000 => 'warn',
            default => 'warn',
        };

        return $this->result(
            'html-size',
            'HTML payload',
            $status,
            $status === 'pass'
                ? "Home HTML is about {$kb} KB."
                : "Home HTML is about {$kb} KB. Large documents slow first paint.",
        );
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    protected function scriptCount(string $html): array
    {
        preg_match_all('/<script\b/i', $html, $matches);
        $count = count($matches[0]);

        return $this->result(
            'scripts',
            'Script tags',
            $count <= 8 ? 'pass' : 'warn',
            $count <= 8
                ? "{$count} script tag".($count === 1 ? '' : 's').' on the page.'
                : "{$count} script tags may delay interactivity. Defer or bundle where possible.",
        );
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    protected function fontLoading(string $html): array
    {
        $hasPreload = preg_match('/<link[^>]+rel=["\']preload["\'][^>]+as=["\']font["\']/i', $html) === 1;
        $hasDisplaySwap = str_contains($html, 'font-display:swap') || str_contains($html, 'display=swap');

        $ok = $hasPreload || $hasDisplaySwap;

        return $this->result(
            'fonts',
            'Font loading',
            $ok ? 'pass' : 'warn',
            $ok
                ? 'Fonts use preload or display swap hints.'
                : 'No font preload or display swap hint found. Text may flash or shift while fonts load.',
        );
    }

    /**
     * Cheap LCP proxy: the first content image should not lazy-load without priority.
     *
     * @return array{key: string, label: string, status: string, detail: string}
     */
    protected function heroImageHint(string $html): array
    {
        preg_match_all('/<img\b[^>]*>/i', $html, $tags);

        if ($tags[0] === []) {
            return $this->result(
                'lcp-image',
                'Hero image',
                'pass',
                'No content images to evaluate.',
            );
        }

        $first = $tags[0][0];
        $lazy = preg_match('/\bloading=["\']lazy["\']/i', $first) === 1;
        $priority = preg_match('/\bfetchpriority=["\']high["\']/i', $first) === 1;

        $ok = ! $lazy || $priority;

        return $this->result(
            'lcp-image',
            'Hero image',
            $ok ? 'pass' : 'warn',
            $ok
                ? 'The first image is not deferred without a priority hint.'
                : 'The first image uses lazy loading, which can delay the largest paint.',
        );
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    protected function result(string $key, string $label, string $status, string $detail): array
    {
        return compact('key', 'label', 'status', 'detail');
    }
}

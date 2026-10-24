<?php

namespace App\Services\SiteBuilder;

use App\Models\Site;

/**
 * Builds the links between the pages of one website, for wherever it is being served.
 */
class SiteUrls
{
    final protected function __construct(
        protected string $origin,
        protected string $base,
        protected string $query,
        protected bool $trailingSlash,
        protected string $locale,
        protected string $defaultLocale,
    ) {}

    /**
     * Links for the shareable preview, which lives under /p/{slug}.
     */
    public static function preview(Site $site, bool $internal = false, bool $editor = false, ?string $locale = null): static
    {
        $site->loadMissing('team');
        $default = SiteLocales::default($site->content);
        $locale ??= $default;

        return new static(
            $site->team?->brandedPreviewOrigin() ?? rtrim((string) config('app.url'), '/'),
            route('sites.preview', $site->slug, false),
            $editor ? '?internal=1&editor=1' : ($internal ? '?internal=1' : ''),
            false,
            $locale,
            $default,
        );
    }

    /**
     * Links for a site served from the root of its own domain, or from an exported folder.
     */
    public static function root(string $origin = '', bool $trailingSlash = false, string $locale = SiteLocales::DEFAULT, string $defaultLocale = SiteLocales::DEFAULT): static
    {
        return new static(rtrim($origin, '/'), '', '', $trailingSlash, $locale, $defaultLocale);
    }

    /**
     * Get the link to a page, relative to the host. An empty path is the home page.
     */
    public function to(string $path = ''): string
    {
        return $this->path($path).$this->query;
    }

    /**
     * Get the full address of a page for canonical links and the sitemap.
     */
    public function absolute(string $path = ''): string
    {
        return $this->origin.$this->path($path);
    }

    /**
     * Whether the links point at the preview rather than a published site.
     */
    public function isPreview(): bool
    {
        return $this->base !== '';
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function defaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Build URLs for another locale while keeping the same origin and base.
     */
    public function forLocale(string $locale): static
    {
        return new static($this->origin, $this->base, $this->query, $this->trailingSlash, $locale, $this->defaultLocale);
    }

    protected function path(string $path): string
    {
        $path = trim($path, '/');
        $prefix = $this->localePrefix();

        if ($path === '') {
            if ($prefix === '') {
                return $this->base === '' ? '/' : $this->base;
            }

            return $this->base === '' ? '/'.$prefix : $this->base.'/'.$prefix;
        }

        $segments = $prefix === '' ? $path : $prefix.'/'.$path;

        return $this->base.'/'.$segments.($this->trailingSlash ? '/' : '');
    }

    protected function localePrefix(): string
    {
        if ($this->locale === $this->defaultLocale) {
            return '';
        }

        return $this->locale;
    }
}

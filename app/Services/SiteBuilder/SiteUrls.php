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
    ) {}

    /**
     * Links for the shareable preview, which lives under /p/{slug}.
     */
    public static function preview(Site $site, bool $internal = false): static
    {
        return new static(
            rtrim(url('/'), '/'),
            route('sites.preview', $site->slug, false),
            $internal ? '?internal=1' : '',
            false,
        );
    }

    /**
     * Links for a site served from the root of its own domain, or from an exported folder.
     */
    public static function root(string $origin = '', bool $trailingSlash = false): static
    {
        return new static(rtrim($origin, '/'), '', '', $trailingSlash);
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

    protected function path(string $path): string
    {
        $path = trim($path, '/');

        if ($path === '') {
            return $this->base === '' ? '/' : $this->base;
        }

        return $this->base.'/'.$path.($this->trailingSlash ? '/' : '');
    }
}

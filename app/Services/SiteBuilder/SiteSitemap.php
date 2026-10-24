<?php

namespace App\Services\SiteBuilder;

use App\Models\Site;

/**
 * Builds the sitemap.xml and robots.txt for a published website.
 */
class SiteSitemap
{
    public function __construct(protected SitePages $pages) {}

    /**
     * Build a sitemap listing every page of the site.
     */
    public function xml(Site $site, SiteUrls $urls): string
    {
        $posts = $this->pages->posts($site)->keyBy('slug');
        $lastModified = $site->updated_at;
        $entries = [];

        foreach (SiteLocales::available($site->content) as $locale) {
            $localizedUrls = $urls->forLocale($locale);

            foreach ($this->pages->paths($site) as $path) {
                $modified = str_starts_with($path, 'blog/')
                    ? ($posts->get(substr($path, 5))?->updated_at ?? $lastModified)
                    : $lastModified;

                $entries[] = sprintf(
                    "  <url>\n    <loc>%s</loc>\n    <lastmod>%s</lastmod>\n  </url>",
                    htmlspecialchars($localizedUrls->absolute($path), ENT_XML1 | ENT_QUOTES),
                    $modified?->toDateString() ?? now()->toDateString(),
                );
            }
        }

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            .implode("\n", $entries)
            ."\n</urlset>\n";
    }

    /**
     * Build a robots.txt that welcomes crawlers and points them at the sitemap.
     */
    public function robots(SiteUrls $urls): string
    {
        return "User-agent: *\nAllow: /\n\nSitemap: ".$urls->absolute('sitemap.xml')."\n";
    }
}

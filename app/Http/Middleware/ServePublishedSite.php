<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Models\SiteDomain;
use App\Services\SiteBuilder\SiteCache;
use App\Services\SiteBuilder\SiteRenderer;
use App\Services\SiteBuilder\SiteSitemap;
use App\Services\SiteBuilder\SiteUrls;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers requests for a customer's own domain with their published website.
 *
 * It runs before sessions, cookies and CSRF so visitors get plain, cacheable pages.
 */
class ServePublishedSite
{
    public function __construct(
        protected SiteRenderer $renderer,
        protected SiteSitemap $sitemap,
        protected SiteCache $cache,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if ($this->isAppHost($host)) {
            return $next($request);
        }

        $domain = SiteDomain::query()->verified()->where('hostname', $host)->with('site')->first();
        $site = $domain?->site;

        if (! $site || ! $site->isPublished()) {
            return $this->notFound();
        }

        $primary = $site->primaryDomain() ?? $domain;

        if ($primary->hostname !== $host) {
            return redirect()->away('https://'.$primary->hostname.$request->getRequestUri(), 301);
        }

        if (config('sites.force_https') && ! $request->isSecure()) {
            return redirect()->away('https://'.$host.$request->getRequestUri(), 301);
        }

        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return response('Method not allowed', 405, ['Allow' => 'GET, HEAD']);
        }

        $requested = (string) parse_url($request->getRequestUri(), PHP_URL_PATH);
        $path = trim($requested, '/');

        if ($requested !== '/' && str_ends_with($requested, '/')) {
            $query = $request->getQueryString();

            return redirect()->away('https://'.$host.'/'.$path.($query ? '?'.$query : ''), 301);
        }

        return $this->page($request, $site, $host, $path);
    }

    protected function page(Request $request, Site $site, string $host, string $path): Response
    {
        $urls = SiteUrls::root('https://'.$host);

        if ($path === 'robots.txt') {
            $body = $this->cache->remember($site->id, "robots:{$host}", fn () => $this->sitemap->robots($urls));

            return $this->cacheable($request, $body, 'text/plain; charset=UTF-8');
        }

        if ($path === 'sitemap.xml') {
            $body = $this->cache->remember($site->id, "sitemap:{$host}", fn () => $this->sitemap->xml($site, $urls));

            return $this->cacheable($request, $body, 'application/xml; charset=UTF-8');
        }

        $html = $this->cache->remember($site->id, "html:{$host}:{$path}", fn () => $this->renderer->render($site, false, $path, $urls));

        if ($html === null) {
            return $this->notFound();
        }

        return $this->cacheable($request, $html, 'text/html; charset=UTF-8');
    }

    /**
     * Wrap a body in headers that let browsers and CDNs reuse it.
     */
    protected function cacheable(Request $request, ?string $body, string $contentType): Response
    {
        $response = response($body ?? '', 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => sprintf(
                'public, max-age=%d, stale-while-revalidate=%d',
                config('sites.cache_seconds'),
                config('sites.stale_seconds'),
            ),
            'ETag' => '"'.md5((string) $body).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Vary' => 'Accept-Encoding',
        ]);

        $response->isNotModified($request);

        return $response;
    }

    protected function notFound(): Response
    {
        return response(
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Page not found</title></head>'
            .'<body style="font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0"><main style="text-align:center"><h1>Page not found</h1><p>This page does not exist.</p></main></body></html>',
            404,
            ['Content-Type' => 'text/html; charset=UTF-8', 'X-Robots-Tag' => 'noindex'],
        );
    }

    /**
     * Whether the host belongs to the dashboard rather than a customer's site.
     */
    protected function isAppHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return true;
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return $host === $appHost || in_array($host, array_map('strtolower', config('sites.app_hosts')), true);
    }
}

<?php

namespace App\Services\SiteBuilder;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps rendered pages of published sites so visitors never wait for a render.
 *
 * Every page of a site shares one version number. Changing the site, its posts,
 * its business details or its domains bumps the version, which retires all its pages at once.
 */
class SiteCache
{
    /**
     * Get the cached value, building it first when missing.
     *
     * @template T of string|null
     *
     * @param  Closure(): T  $build
     * @return T
     */
    public function remember(int $siteId, string $key, Closure $build): ?string
    {
        $cacheKey = $this->pageKey($siteId, $key);

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached === '' ? null : $cached;
        }

        $value = $build();

        // Builders may flush the version (e.g. first-time brand assets). Store under the
        // version that is current after the build so the next request can hit this entry.
        Cache::put($this->pageKey($siteId, $key), $value ?? '', config('sites.html_cache_seconds'));

        return $value;
    }

    protected function pageKey(int $siteId, string $key): string
    {
        return 'site-page:'.$siteId.':'.$this->version($siteId).':'.md5($key);
    }

    /**
     * Retire every cached page of the site.
     */
    public static function flush(int $siteId): void
    {
        Cache::forever('site-version:'.$siteId, (string) microtime(true));
    }

    protected function version(int $siteId): string
    {
        return (string) Cache::get('site-version:'.$siteId, '0');
    }
}

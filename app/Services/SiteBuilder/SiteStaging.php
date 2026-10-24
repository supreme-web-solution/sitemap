<?php

namespace App\Services\SiteBuilder;

use App\Models\Site;

/**
 * Production-like staging hostnames under the app edge ({slug}.staging.{base}).
 */
class SiteStaging
{
    /**
     * Hostname suffix customers' staging sites use (e.g. staging.yourapp.com).
     */
    public static function baseHost(): string
    {
        $configured = strtolower(trim((string) config('sites.staging_base_host')));

        if ($configured !== '') {
            return $configured;
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return 'staging.'.$appHost;
    }

    /**
     * Full staging hostname for a site.
     */
    public static function hostname(Site $site): string
    {
        return strtolower($site->slug.'.'.self::baseHost());
    }

    /**
     * HTTPS origin for staging links and canonical URLs.
     */
    public static function origin(Site $site): string
    {
        return 'https://'.self::hostname($site);
    }

    /**
     * Resolve a site slug from a request host, or null when this is not a staging host.
     */
    public static function slugFromHost(string $host): ?string
    {
        $host = strtolower($host);
        $suffix = '.'.self::baseHost();

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $slug = substr($host, 0, -strlen($suffix));

        return $slug !== '' ? $slug : null;
    }
}

<?php

namespace App\Services\Preview;

use App\Models\Site;

/**
 * Builds preview URLs, using the agency's verified preview hostname when set.
 */
class PreviewLink
{
    /**
     * Link to a preview page (home or inner path).
     *
     * @param  array<string, mixed>  $query
     */
    public static function page(Site $site, string $path = '', bool $internal = false, array $query = []): string
    {
        $site->loadMissing('team');

        $routeParams = array_filter([
            'site' => $site->slug,
            'path' => $path !== '' ? $path : null,
        ]);

        $relative = route('sites.preview', $routeParams, false);

        if ($internal) {
            $query['internal'] = 1;
        }

        $queryString = http_build_query($query);

        return self::origin($site).$relative.($queryString !== '' ? '?'.$queryString : '');
    }

    /**
     * Link to a named preview action (interest, comments, and so on).
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function action(string $routeName, Site $site, array $parameters = []): string
    {
        $site->loadMissing('team');
        $parameters['site'] = $site->slug;

        return self::origin($site).route($routeName, $parameters, false);
    }

    protected static function origin(Site $site): string
    {
        return $site->team?->brandedPreviewOrigin() ?? rtrim((string) config('app.url'), '/');
    }
}

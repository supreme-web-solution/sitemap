<?php

namespace App\Services\Audit;

class AuditHttpFailure
{
    /**
     * Whether the response looks like a bot challenge rather than a normal denial.
     */
    public static function isBotChallenge(int $status, string $body): bool
    {
        if (! in_array($status, [403, 429, 503], true)) {
            return false;
        }

        $markers = [
            'Just a moment',
            'cf-browser-verification',
            'challenge-platform',
            'Attention Required! | Cloudflare',
            'Enable JavaScript and cookies to continue',
            '_cf_chl',
        ];

        foreach ($markers as $marker) {
            if (str_contains($body, $marker)) {
                return true;
            }
        }

        return false;
    }

    public static function message(int $status, string $body): string
    {
        if (self::isBotChallenge($status, $body)) {
            if (filled(config('prospecting.audit.scraper_api_key'))) {
                return __('This site uses bot protection. We tried a proxy fetch but still could not read the page.');
            }

            return __('This site uses bot protection and blocked our request. Ask your admin to set SCRAPER_API_KEY for protected sites, or review the site manually.');
        }

        return __('The website responded with HTTP :status.', ['status' => $status]);
    }

    /**
     * @param  array{status: int, body: string}  $page
     */
    public static function shouldRetryViaProxy(array $page): bool
    {
        if (blank(config('prospecting.audit.scraper_api_key'))) {
            return false;
        }

        if ($page['status'] >= 200 && $page['status'] < 300) {
            return false;
        }

        return self::isBotChallenge($page['status'], $page['body'])
            || in_array($page['status'], [403, 429], true);
    }
}

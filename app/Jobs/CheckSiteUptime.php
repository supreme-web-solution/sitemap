<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\Audit\SafeHttpFetcher;
use App\Services\Audit\UnsafeUrlException;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;

class CheckSiteUptime implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 40;

    public int $tries = 1;

    public function __construct(public Site $site)
    {
        $this->onQueue(Queues::MONITORING);
    }

    public function uniqueId(): string
    {
        return 'uptime-'.$this->site->id;
    }

    /**
     * Ping the live domain (or the app-host preview) and store whether it answered.
     */
    public function handle(SafeHttpFetcher $fetcher): void
    {
        $url = $this->url();

        try {
            $result = $fetcher->fetch($url);
            $ok = $result['status'] < 500;
        } catch (UnsafeUrlException|ConnectionException) {
            $ok = false;
        }

        $this->site->forceFill([
            'last_uptime_at' => now(),
            'uptime_ok' => $ok,
        ])->save();
    }

    protected function url(): string
    {
        $domain = $this->site->primaryDomain();

        if ($domain !== null) {
            return 'https://'.$domain->hostname;
        }

        // Keep monitoring on the app host. Branded preview hostnames can pass DNS
        // while Caddy is still issuing HTTPS, which would false-fail uptime.
        return rtrim((string) config('app.url'), '/').route('sites.preview', [
            'site' => $this->site->slug,
        ], absolute: false);
    }
}

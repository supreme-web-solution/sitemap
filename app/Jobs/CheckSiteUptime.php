<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\Audit\SafeHttpFetcher;
use App\Services\Audit\UnsafeUrlException;
use App\Services\Preview\PreviewLink;
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
     * Ping the live domain (or the preview) and store whether it answered.
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

        return $domain !== null
            ? 'https://'.$domain->hostname
            : PreviewLink::page($this->site);
    }
}

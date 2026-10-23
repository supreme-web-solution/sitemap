<?php

namespace App\Console\Commands;

use App\Jobs\CheckSiteUptime;
use App\Jobs\CrawlSiteLinks;
use App\Models\Site;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;

#[Description('Queue uptime and broken-link checks for published sites')]
class MonitorPublishedSites extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sites:monitor';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Site::query()
            ->whereNotNull('published_at')
            ->orderBy('id')
            ->eachById(function (Site $site) {
                CheckSiteUptime::dispatch($site);

                if ($site->last_crawled_at === null || $site->last_crawled_at->lt(now()->subDay())) {
                    CrawlSiteLinks::dispatch($site);
                }
            }, 50);

        return self::SUCCESS;
    }
}

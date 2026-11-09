<?php

namespace App\Jobs;

use App\Models\SiteDomain;
use App\Models\Team;
use App\Services\Cloudflare\CloudflareCustomHostnames;
use App\Services\Cloudflare\SyncCloudflareHostname;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Poll Cloudflare until pending custom hostnames report active SSL.
 */
class RefreshCloudflareHostnameStatuses implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 50;

    public function __construct()
    {
        $this->onQueue(Queues::DEFAULT);
    }

    public function handle(CloudflareCustomHostnames $cloudflare, SyncCloudflareHostname $sync): void
    {
        if (! $cloudflare->enabled()) {
            return;
        }

        SiteDomain::query()
            ->whereNotNull('cloudflare_hostname_id')
            ->whereNotNull('verified_at')
            ->where(function ($query) {
                $query->whereNull('cloudflare_status')
                    ->orWhere('cloudflare_status', '!=', 'active')
                    ->orWhereNull('cloudflare_ssl_status')
                    ->orWhere('cloudflare_ssl_status', '!=', 'active');
            })
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->each(fn (SiteDomain $domain) => $sync->syncSiteDomain($domain));

        Team::query()
            ->whereNotNull('preview_cloudflare_hostname_id')
            ->whereNotNull('preview_hostname_verified_at')
            ->where(function ($query) {
                $query->whereNull('preview_cloudflare_status')
                    ->orWhere('preview_cloudflare_status', '!=', 'active')
                    ->orWhereNull('preview_cloudflare_ssl_status')
                    ->orWhere('preview_cloudflare_ssl_status', '!=', 'active');
            })
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->each(fn (Team $team) => $sync->syncTeamPreview($team));
    }
}

<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Jobs\CheckSiteUptime;
use App\Jobs\CrawlSiteLinks;
use App\Models\Site;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SiteHealthController extends Controller
{
    /**
     * Queue a broken-link crawl and an uptime check.
     */
    public function store(Team $currentTeam, Site $site): RedirectResponse
    {
        CrawlSiteLinks::dispatch($site);
        CheckSiteUptime::dispatch($site);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Health checks queued.')]);

        return back();
    }
}

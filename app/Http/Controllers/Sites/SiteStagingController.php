<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Team;
use App\Services\SiteBuilder\SiteCache;
use App\Services\SiteBuilder\SiteQualityChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SiteStagingController extends Controller
{
    /**
     * Put the current site on its staging hostname (no custom domain required).
     */
    public function store(Team $currentTeam, Site $site): RedirectResponse
    {
        $site->forceFill(['staging_published_at' => $site->staging_published_at ?? now()])->save();
        SiteCache::flush($site->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Staging site is live.')]);

        return back();
    }

    /**
     * Take the staging hostname offline.
     */
    public function destroy(Team $currentTeam, Site $site): RedirectResponse
    {
        $site->forceFill(['staging_published_at' => null])->save();
        SiteCache::flush($site->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Staging site removed.')]);

        return back();
    }

    /**
     * Promote the current site to production on its verified domains.
     */
    public function promote(Team $currentTeam, Site $site, SiteQualityChecker $quality): RedirectResponse
    {
        if ($site->primaryDomain() === null) {
            throw ValidationException::withMessages([
                'publish' => __('Connect and verify a domain before publishing.'),
            ]);
        }

        $failed = collect($quality->check($site)['checks'])->firstWhere('status', 'fail');

        if ($failed !== null) {
            throw ValidationException::withMessages([
                'publish' => __('Fix this before publishing: :problem', ['problem' => $failed['detail']]),
            ]);
        }

        $site->forceFill(['published_at' => $site->published_at ?? now()])->save();
        SiteCache::flush($site->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Website published to production.')]);

        return back();
    }
}

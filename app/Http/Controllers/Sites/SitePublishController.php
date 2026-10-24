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

class SitePublishController extends Controller
{
    /**
     * Put the website live on its verified domains.
     */
    public function store(Team $currentTeam, Site $site, SiteQualityChecker $quality): RedirectResponse
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

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Website published.')]);

        return back();
    }

    /**
     * Take the website offline. Its domains stay connected.
     */
    public function destroy(Team $currentTeam, Site $site): RedirectResponse
    {
        $site->forceFill(['published_at' => null])->save();
        SiteCache::flush($site->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Website unpublished.')]);

        return back();
    }
}

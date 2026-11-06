<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteVersion;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SiteVersionController extends Controller
{
    /**
     * Restore the site to a saved revision, keeping the current state as a revision too.
     */
    public function restore(Team $currentTeam, Site $site, SiteVersion $version): RedirectResponse
    {
        $site->snapshot(auth()->id(), __('Before restoring an earlier version'));

        $site->update([
            'template' => $version->template,
            'content' => $version->content,
        ]);

        if (is_array($version->media) && $site->lead !== null) {
            $site->lead->forceFill([
                'photo_paths' => $version->media['photo_paths'] ?? null,
                'logo_path' => array_key_exists('logo_path', $version->media)
                    ? $version->media['logo_path']
                    : $site->lead->logo_path,
            ])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Earlier version restored.')]);

        return back();
    }
}

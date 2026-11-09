<?php

namespace App\Http\Controllers\PitchVideos;

use App\Enums\LeadActivityType;
use App\Http\Controllers\Controller;
use App\Models\SitePitchVideo;
use App\Services\Preview\PreviewLink;
use Illuminate\View\View;

class PitchVideoPublicController extends Controller
{
    /**
     * Show the sales video to the business owner.
     */
    public function show(SitePitchVideo $pitchVideo): View
    {
        abort_unless($pitchVideo->isWatchable(), 404);

        $pitchVideo->load('team', 'site.lead');

        $firstView = $pitchVideo->viewed_at === null;

        $pitchVideo->newQuery()->whereKey($pitchVideo->id)->incrementEach(
            ['views_count' => 1],
            ['viewed_at' => $pitchVideo->viewed_at ?? now()],
        );

        if ($firstView) {
            $pitchVideo->site->lead->recordActivity(
                LeadActivityType::PitchVideoViewed,
                __('Watched the sales video'),
                ['site_id' => $pitchVideo->site_id],
            );
        }

        return view('pitch-videos.public', [
            'pitchVideo' => $pitchVideo,
            'previewUrl' => PreviewLink::page($pitchVideo->site),
        ]);
    }
}

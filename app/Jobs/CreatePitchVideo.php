<?php

namespace App\Jobs;

use App\Enums\PitchVideoStatus;
use App\Models\SitePitchVideo;
use App\Services\Did\DidClient;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class CreatePitchVideo implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    public function __construct(public SitePitchVideo $video)
    {
        $this->onQueue(Queues::DEFAULT);
    }

    /**
     * Ask D-ID to render the video, then start checking on it.
     */
    public function handle(DidClient $did): void
    {
        $video = $this->video->fresh();

        if ($video === null || $video->status !== PitchVideoStatus::Generating || filled($video->did_clip_id)) {
            return;
        }

        $clipId = $did->createClip($video->presenter_id, $video->script, [
            'id' => $video->voice_id,
            'provider' => $video->voice_provider ?: 'microsoft',
        ]);

        $video->forceFill(['did_clip_id' => $clipId])->save();

        RefreshPitchVideo::dispatch($video)->delay(now()->addSeconds(RefreshPitchVideo::POLL_SECONDS));
    }

    public function failed(Throwable $exception): void
    {
        $this->video->fresh()?->forceFill([
            'status' => PitchVideoStatus::Failed,
            'error' => Str::limit($exception->getMessage(), 250),
        ])->save();
    }
}

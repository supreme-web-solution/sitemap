<?php

namespace App\Jobs;

use App\Enums\PitchVideoStatus;
use App\Models\SitePitchVideo;
use App\Services\Did\DidClient;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RefreshPitchVideo implements ShouldQueue
{
    use Queueable;

    /**
     * Seconds between checks while D-ID renders the video.
     */
    public const POLL_SECONDS = 15;

    /**
     * Minutes after which a video that has not finished is given up on.
     */
    public const GIVE_UP_MINUTES = 20;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 150;

    public function __construct(public SitePitchVideo $video)
    {
        $this->onQueue(Queues::DEFAULT);
    }

    /**
     * Check D-ID, then store the video once it is finished.
     */
    public function handle(DidClient $did): void
    {
        $video = $this->video->fresh();

        if ($video === null || $video->status !== PitchVideoStatus::Generating || blank($video->did_clip_id)) {
            return;
        }

        try {
            $clip = $did->clip($video->did_clip_id);
        } catch (RuntimeException) {
            $clip = ['id' => $video->did_clip_id, 'status' => 'started', 'result_url' => null, 'error' => null];
        }

        if ($clip['status'] === 'done' && $clip['result_url'] !== null) {
            $path = "teams/{$video->team_id}/pitch-videos/{$video->public_token}.mp4";

            Storage::disk('public')->put($path, $did->download($clip['result_url']));

            $video->forceFill([
                'status' => PitchVideoStatus::Ready,
                'video_path' => $path,
                'error' => null,
                'generated_at' => now(),
            ])->save();

            return;
        }

        if (in_array($clip['status'], ['error', 'rejected'], true)) {
            $this->fail($video, $clip['error'] ?? __('D-ID could not render this video. Try a different script or presenter.'));

            return;
        }

        if ($video->generation_started_at !== null && $video->generation_started_at->lt(now()->subMinutes(self::GIVE_UP_MINUTES))) {
            $this->fail($video, __('The video took too long to render. Try again.'));

            return;
        }

        self::dispatch($video)->delay(now()->addSeconds(self::POLL_SECONDS));
    }

    public function failed(Throwable $exception): void
    {
        $video = $this->video->fresh();

        if ($video !== null && $video->status === PitchVideoStatus::Generating) {
            $this->fail($video, $exception->getMessage());
        }
    }

    protected function fail(SitePitchVideo $video, string $message): void
    {
        $video->forceFill([
            'status' => PitchVideoStatus::Failed,
            'error' => Str::limit($message, 250),
        ])->save();
    }
}

<?php

namespace App\Http\Controllers\Sites;

use App\Enums\PitchVideoStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sites\GeneratePitchVideoRequest;
use App\Jobs\CreatePitchVideo;
use App\Models\Site;
use App\Models\SitePitchVideo;
use App\Models\Team;
use App\Services\Did\DidClient;
use App\Services\PitchVideos\PitchScriptGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;

class SitePitchVideoController extends Controller
{
    /**
     * Write the presenter's script for this website with AI.
     */
    public function script(Team $currentTeam, Site $site, PitchScriptGenerator $scripts): RedirectResponse
    {
        $video = $this->videoFor($site);

        if ($video->isGenerating()) {
            throw ValidationException::withMessages(['script' => __('Wait for the video to finish before changing the script.')]);
        }

        $video->forceFill(['script' => $scripts->generate($site)])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Script written. Read it through, then create the video.')]);

        return back();
    }

    /**
     * Start creating the video with the chosen presenter, voice, and script.
     */
    public function store(GeneratePitchVideoRequest $request, Team $currentTeam, Site $site, DidClient $did): RedirectResponse
    {
        if (! $did->configured()) {
            throw ValidationException::withMessages(['presenter_id' => __('Video creation is not set up yet. Add DID_API_KEY in your environment.')]);
        }

        $video = $this->videoFor($site);

        if ($video->isGenerating()) {
            throw ValidationException::withMessages(['presenter_id' => __('A video is already being created for this website.')]);
        }

        $presenter = $this->findPresenter($did, $request->validated('presenter_id'));
        $voice = $this->findVoice($did, $request->validated('voice_id'));

        $video->forceFill([
            'status' => PitchVideoStatus::Generating,
            'script' => $request->validated('script'),
            'presenter_id' => $presenter['id'],
            'presenter_name' => $presenter['name'],
            'presenter_gender' => $presenter['gender'],
            'presenter_thumbnail_url' => $presenter['thumbnail_url'],
            'voice_id' => $voice['id'],
            'voice_name' => $voice['name'],
            'voice_provider' => $voice['provider'],
            'did_clip_id' => null,
            'error' => null,
            'generation_started_at' => now(),
        ])->save();

        CreatePitchVideo::dispatch($video);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Creating your video. This usually takes a minute or two.')]);

        return back();
    }

    /**
     * Remove the video and its file.
     */
    public function destroy(Team $currentTeam, Site $site): RedirectResponse
    {
        $video = $site->pitchVideo;

        if ($video !== null) {
            if (filled($video->video_path)) {
                Storage::disk('public')->delete($video->video_path);
            }

            $video->delete();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Video removed.')]);

        return back();
    }

    protected function videoFor(Site $site): SitePitchVideo
    {
        return $site->pitchVideo ?? $site->pitchVideo()->create([
            'team_id' => $site->team_id,
            'status' => PitchVideoStatus::Draft,
        ]);
    }

    /**
     * @return array{id: string, name: string, gender: string|null, thumbnail_url: string|null}
     */
    protected function findPresenter(DidClient $did, string $id): array
    {
        try {
            $presenter = collect($did->presenters())->firstWhere('id', $id);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['presenter_id' => $exception->getMessage()]);
        }

        return $presenter ?? throw ValidationException::withMessages(['presenter_id' => __('That presenter is no longer available. Choose another.')]);
    }

    /**
     * @return array{id: string, name: string, gender: string|null, locale: string, provider: string, preview_url: string|null}
     */
    protected function findVoice(DidClient $did, string $id): array
    {
        try {
            $voice = collect($did->voices())->firstWhere('id', $id);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['voice_id' => $exception->getMessage()]);
        }

        return $voice ?? throw ValidationException::withMessages(['voice_id' => __('That voice is no longer available. Choose another.')]);
    }
}

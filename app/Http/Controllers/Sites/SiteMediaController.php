<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\UpdateLeadProfileRequest;
use App\Models\Lead;
use App\Models\Site;
use App\Models\Team;
use App\Services\SiteBuilder\OpenAiImageGenerator;
use App\Services\SiteBuilder\SiteMediaUndo;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SiteMediaController extends Controller
{
    /**
     * Replace the logo, replace one photo, or add a photo, from the visual editor.
     */
    public function store(Request $request, Team $currentTeam, Site $site, OpenAiImageGenerator $images, SiteMediaUndo $mediaUndo): JsonResponse
    {
        if ($request->boolean('generate')) {
            return $this->generated($request, $currentTeam, $site, $images, $mediaUndo);
        }

        $data = $request->validate([
            'slot' => ['required', 'in:logo,photo'],
            'index' => ['nullable', 'integer', 'min:0', 'max:'.(UpdateLeadProfileRequest::MAX_PHOTOS - 1)],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.($request->input('slot') === 'logo' ? 2048 : 5120)],
        ]);

        $lead = $site->lead;
        $mediaUndo->remember($site, $lead);
        $disk = Storage::disk('public');
        $stored = $request->file('image')->store("teams/{$currentTeam->id}/leads/{$lead->id}", 'public');

        if ($data['slot'] === 'logo') {
            $this->forget($lead->logo_path);
            $lead->logo_path = $stored;
        } else {
            $paths = $lead->photo_paths ?? [];
            $index = $data['index'] ?? count($paths);

            if ($index >= count($paths) && count($paths) >= UpdateLeadProfileRequest::MAX_PHOTOS) {
                $disk->delete($stored);

                throw ValidationException::withMessages(['image' => __('A website can have up to :max photos.', ['max' => UpdateLeadProfileRequest::MAX_PHOTOS])]);
            }

            if (isset($paths[$index])) {
                $this->forget($paths[$index]);
                $paths[$index] = $stored;
            } else {
                $paths[] = $stored;
            }

            $lead->photo_paths = array_values($paths);
        }

        $lead->save();

        return $this->media($lead);
    }

    /**
     * Remove the logo or one uploaded photo.
     */
    public function destroy(Request $request, Team $currentTeam, Site $site, SiteMediaUndo $mediaUndo): JsonResponse
    {
        $data = $request->validate([
            'slot' => ['required', 'in:logo,photo'],
            'index' => ['nullable', 'integer', 'min:0', 'max:'.(UpdateLeadProfileRequest::MAX_PHOTOS - 1)],
        ]);

        $lead = $site->lead;
        $mediaUndo->remember($site, $lead);

        if ($data['slot'] === 'logo') {
            $this->forget($lead->logo_path);
            $lead->logo_path = null;
        } else {
            $paths = $lead->photo_paths ?? [];
            $index = $data['index'] ?? -1;

            if (! isset($paths[$index])) {
                throw ValidationException::withMessages(['index' => __('That photo is not an uploaded photo.')]);
            }

            $this->forget($paths[$index]);
            unset($paths[$index]);
            $lead->photo_paths = array_values($paths) ?: null;
        }

        $lead->save();

        return $this->media($lead);
    }

    /**
     * Restore logo and photos from the last visual-editor media change.
     */
    public function undo(Team $currentTeam, Site $site, SiteMediaUndo $mediaUndo): JsonResponse
    {
        $snapshot = $mediaUndo->pull($site);

        if ($snapshot === null) {
            abort(404);
        }

        $lead = $site->lead;

        $this->forget($lead->logo_path);

        foreach ($lead->photo_paths ?? [] as $path) {
            $this->forget($path);
        }

        $lead->logo_path = $snapshot['logo_path'];
        $photoPaths = array_values(
            array_filter(
                $snapshot['photo_paths'] ?? [],
                fn (?string $path) => $path !== null,
            ),
        );
        $lead->photo_paths = $photoPaths === [] ? null : $photoPaths;
        $lead->save();

        return $this->media($lead);
    }

    protected function forget(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function media(Lead $lead): JsonResponse
    {
        return response()->json([
            'logo' => $lead->logoUrl(),
            'photos' => $lead->photoUrls(),
        ]);
    }

    protected function generated(Request $request, Team $currentTeam, Site $site, OpenAiImageGenerator $images, SiteMediaUndo $mediaUndo): JsonResponse
    {
        $data = $request->validate([
            'slot' => ['required', 'in:photo'],
            'index' => ['nullable', 'integer', 'min:0', 'max:'.(UpdateLeadProfileRequest::MAX_PHOTOS - 1)],
            'prompt' => ['required', 'string', 'max:500'],
        ]);

        $lead = $site->lead;
        $mediaUndo->remember($site, $lead);

        try {
            $generated = $images->generate(
                $data['prompt'],
                $currentTeam->id,
                $lead->id,
            );
        } catch (ConnectionException|\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'prompt' => $exception->getMessage(),
            ]);
        }

        $paths = $lead->photo_paths ?? [];
        $index = $data['index'] ?? count($paths);

        if ($index >= count($paths) && count($paths) >= UpdateLeadProfileRequest::MAX_PHOTOS) {
            Storage::disk('public')->delete($generated['path']);

            throw ValidationException::withMessages(['prompt' => __('A website can have up to :max photos.', ['max' => UpdateLeadProfileRequest::MAX_PHOTOS])]);
        }

        if (isset($paths[$index])) {
            $this->forget($paths[$index]);
            $paths[$index] = $generated['path'];
        } else {
            $paths[] = $generated['path'];
        }

        $lead->photo_paths = array_values($paths);
        $lead->save();

        return $this->media($lead);
    }
}

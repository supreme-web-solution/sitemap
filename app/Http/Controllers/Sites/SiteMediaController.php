<?php

namespace App\Http\Controllers\Sites;

use App\Data\SiteImageGenerationPlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\UpdateLeadProfileRequest;
use App\Models\Lead;
use App\Models\Site;
use App\Models\Team;
use App\Services\Media\SiteImageStorage;
use App\Services\SiteBuilder\LeadPhotoGenerator;
use App\Services\SiteBuilder\SiteImageGenerationPlanner;
use App\Services\SiteBuilder\SiteImagePlacements;
use App\Services\SiteBuilder\SiteImagePromptBuilder;
use App\Services\SiteBuilder\SiteMediaUndo;
use App\Services\SiteBuilder\SitePages;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SiteMediaController extends Controller
{
    private const MAX_SERVICE_INDEX = 19;

    /**
     * Replace the logo, a lead photo, or a service card image from the visual editor.
     */
    public function store(
        Request $request,
        Team $currentTeam,
        Site $site,
        LeadPhotoGenerator $images,
        SiteImageGenerationPlanner $imagePlanner,
        SiteMediaUndo $mediaUndo,
        SiteImageStorage $imageStorage,
    ): JsonResponse {
        if ($request->boolean('generate') || $request->boolean('improve')) {
            return $this->generated($request, $currentTeam, $site, $images, $imagePlanner, $mediaUndo, $imageStorage);
        }

        $data = $request->validate([
            'slot' => ['required', Rule::in($this->slots())],
            'index' => ['nullable', 'integer', 'min:0', 'max:'.$this->maxIndexForSlot((string) $request->input('slot'))],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.($request->input('slot') === 'logo' ? 2048 : 5120)],
        ]);

        $lead = $site->lead;
        $mediaUndo->remember($site, $lead);

        try {
            $stored = $imageStorage->storeUploadedFile($request->file('image'), $currentTeam->id, $lead->id)['path'];
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['image' => $exception->getMessage()]);
        }

        if ($data['slot'] === 'logo') {
            $imageStorage->forgetReplaced($lead->logo_path);
            $lead->logo_path = $stored;
            $lead->save();

            return $this->media($site, $lead);
        }

        if ($data['slot'] === 'service') {
            $index = $data['index'] ?? null;

            if ($index === null) {
                $imageStorage->delete($stored);

                throw ValidationException::withMessages(['index' => __('Choose which service image to update.')]);
            }

            $this->writeServiceImage($site, (int) $index, $stored, $imageStorage);

            return $this->media($site, $lead);
        }

        if (SiteImagePlacements::is($data['slot'])) {
            $this->writePlacement($site, $data['slot'], $stored, $imageStorage);

            return $this->media($site, $lead);
        }

        $paths = $lead->photo_paths ?? [];
        $index = $data['index'] ?? count($paths);

        if ($index >= count($paths) && count($paths) >= UpdateLeadProfileRequest::MAX_PHOTOS) {
            $imageStorage->delete($stored);

            throw ValidationException::withMessages(['image' => __('A website can have up to :max photos.', ['max' => UpdateLeadProfileRequest::MAX_PHOTOS])]);
        }

        if (isset($paths[$index])) {
            $imageStorage->forgetReplaced($paths[$index]);
            $paths[$index] = $stored;
        } else {
            $paths[] = $stored;
        }

        $lead->photo_paths = array_values($paths);
        $lead->save();

        return $this->media($site, $lead);
    }

    /**
     * Remove the logo, one uploaded photo, or a service card image.
     */
    public function destroy(Request $request, Team $currentTeam, Site $site, SiteMediaUndo $mediaUndo, SiteImageStorage $imageStorage): JsonResponse
    {
        $data = $request->validate([
            'slot' => ['required', Rule::in($this->slots())],
            'index' => ['nullable', 'integer', 'min:0', 'max:'.$this->maxIndexForSlot((string) $request->input('slot'))],
        ]);

        $lead = $site->lead;
        $mediaUndo->remember($site, $lead);

        if ($data['slot'] === 'logo') {
            $imageStorage->forgetReplaced($lead->logo_path);
            $lead->logo_path = null;
            $lead->save();

            return $this->media($site, $lead);
        }

        if ($data['slot'] === 'service') {
            $index = $data['index'] ?? null;

            if ($index === null) {
                throw ValidationException::withMessages(['index' => __('Choose which service image to remove.')]);
            }

            $this->writeServiceImage($site, (int) $index, null, $imageStorage);

            return $this->media($site, $lead);
        }

        if (SiteImagePlacements::is($data['slot'])) {
            $this->writePlacement($site, $data['slot'], null, $imageStorage);

            return $this->media($site, $lead);
        }

        $paths = $lead->photo_paths ?? [];
        $index = $data['index'] ?? -1;

        if (! isset($paths[$index])) {
            throw ValidationException::withMessages(['index' => __('That photo is not an uploaded photo.')]);
        }

        $imageStorage->forgetReplaced($paths[$index]);
        unset($paths[$index]);
        $lead->photo_paths = array_values($paths) ?: null;
        $lead->save();

        return $this->media($site, $lead);
    }

    /**
     * Restore logo, photos, and service images from the last visual-editor media change.
     */
    public function undo(Team $currentTeam, Site $site, SiteMediaUndo $mediaUndo, SiteImageStorage $imageStorage): JsonResponse
    {
        $snapshot = $mediaUndo->pull($site);

        if ($snapshot === null) {
            abort(404);
        }

        $lead = $site->lead;

        $imageStorage->delete($lead->logo_path);

        foreach ($lead->photo_paths ?? [] as $path) {
            $imageStorage->delete($path);
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

        if (array_key_exists('service_image_paths', $snapshot)) {
            $this->restoreServiceImages($site, $snapshot['service_image_paths'] ?? [], $imageStorage);
        }

        if (array_key_exists('placement_paths', $snapshot)) {
            $content = $site->fresh()->content ?? [];
            $images = is_array($content['images'] ?? null) ? $content['images'] : [];

            foreach (SiteImagePlacements::KEYS as $key) {
                $current = is_string($images[$key] ?? null) ? $images[$key] : null;
                $restored = $snapshot['placement_paths'][$key] ?? null;

                if ($current !== null && $current !== $restored) {
                    $imageStorage->delete($current);
                }

                if ($restored === null) {
                    unset($images[$key]);
                } else {
                    $images[$key] = $restored;
                }
            }

            $content['images'] = $images;
            $site->content = $content;
            $site->save();
        }

        return $this->media($site, $lead->fresh());
    }

    protected function media(Site $site, Lead $lead): JsonResponse
    {
        $site->refresh();

        return response()->json([
            'logo' => $lead->fresh()->logoUrl(),
            'photos' => $lead->fresh()->photoUrls(),
            'services' => $this->serviceImageUrls($site),
            'placements' => $this->placementUrls($site, $lead->fresh()),
            'revision' => $site->revision,
        ]);
    }

    protected function generated(
        Request $request,
        Team $currentTeam,
        Site $site,
        LeadPhotoGenerator $images,
        SiteImageGenerationPlanner $imagePlanner,
        SiteMediaUndo $mediaUndo,
        SiteImageStorage $imageStorage,
    ): JsonResponse {
        $data = $request->validate([
            'slot' => ['required', Rule::in(array_values(array_filter(
                $this->slots(),
                fn (string $slot) => $slot !== 'logo',
            )))],
            'index' => ['nullable', 'integer', 'min:0', 'max:'.$this->maxIndexForSlot((string) $request->input('slot'))],
            'prompt' => ['nullable', 'string', 'max:500'],
            'improve' => ['sometimes', 'boolean'],
            'generate' => ['sometimes', 'boolean'],
        ]);

        $improve = $request->boolean('improve');
        $prompt = trim((string) ($data['prompt'] ?? ''));

        if (! $improve && $prompt === '' && $data['slot'] === 'photo') {
            throw ValidationException::withMessages(['prompt' => __('Describe the photo you want.')]);
        }

        $lead = $site->lead;
        $mediaUndo->remember($site, $lead);

        try {
            if ($data['slot'] === 'service') {
                return $this->generatedService($site, $lead, $currentTeam, $images, $data['index'] ?? null, $prompt, $improve, $imageStorage);
            }

            if (SiteImagePlacements::is($data['slot'])) {
                return $this->generatedPlacement($site, $lead, $currentTeam, $images, $data['slot'], $prompt, $improve, $imageStorage);
            }

            $paths = $lead->photo_paths ?? [];
            $index = $data['index'] ?? count($paths);

            if ($improve && ! isset($paths[$index])) {
                throw ValidationException::withMessages(['prompt' => __('Select an existing photo to improve.')]);
            }

            $planPrompt = $improve
                ? SiteImagePromptBuilder::forImprove($lead, $site, $prompt !== '' ? $prompt : null)
                : $prompt;
            $plan = $improve
                ? new SiteImageGenerationPlan(
                    prompt: $planPrompt,
                    aspectRatio: '16:9',
                    placement: 'hero',
                    reasoning: 'Sharpen and clean up an existing website photo.',
                    fromLlm: false,
                )
                : $imagePlanner->plan($site, $lead, $planPrompt, (int) $index);

            $generated = $images->generate(
                $plan,
                $currentTeam->id,
                $lead->id,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (ConnectionException|\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'prompt' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'prompt' => LeadPhotoGenerator::unavailableMessage(),
            ]);
        }

        $paths = $lead->photo_paths ?? [];
        $index = $data['index'] ?? count($paths);

        if ($index >= count($paths) && count($paths) >= UpdateLeadProfileRequest::MAX_PHOTOS) {
            $imageStorage->delete($generated['path']);

            throw ValidationException::withMessages(['prompt' => __('A website can have up to :max photos.', ['max' => UpdateLeadProfileRequest::MAX_PHOTOS])]);
        }

        if (isset($paths[$index])) {
            $imageStorage->forgetReplaced($paths[$index]);
            $paths[$index] = $generated['path'];
        } else {
            $paths[] = $generated['path'];
        }

        $lead->photo_paths = array_values($paths);
        $lead->save();

        return $this->media($site, $lead);
    }

    /**
     * @return list<string|null>
     */
    protected function serviceImageUrls(Site $site): array
    {
        return array_map(
            static function (array $service): ?string {
                return $service['image_url'] ?? null;
            },
            app(SitePages::class)->services($site->content ?? []),
        );
    }

    protected function writeServiceImage(Site $site, int $index, ?string $path, SiteImageStorage $imageStorage): void
    {
        $content = $site->content ?? [];
        $services = array_values($content['services'] ?? []);

        if (! isset($services[$index]) || ! is_array($services[$index])) {
            if ($path !== null) {
                $imageStorage->delete($path);
            }

            throw ValidationException::withMessages(['index' => __('That service image slot is invalid.')]);
        }

        $oldPath = is_string($services[$index]['image_path'] ?? null) ? $services[$index]['image_path'] : null;

        if ($oldPath !== null && $oldPath !== $path) {
            $imageStorage->forgetReplaced($oldPath);
        }

        if ($path === null) {
            unset($services[$index]['image_path']);
        } else {
            $services[$index]['image_path'] = $path;
        }

        $content['services'] = $services;
        $site->content = $content;
        $site->save();
    }

    /**
     * @param  list<?string>  $paths
     */
    protected function restoreServiceImages(Site $site, array $paths, SiteImageStorage $imageStorage): void
    {
        $content = $site->content ?? [];
        $services = array_values($content['services'] ?? []);

        foreach ($services as $index => $service) {
            if (! is_array($service)) {
                continue;
            }

            $current = is_string($service['image_path'] ?? null) ? $service['image_path'] : null;
            $restored = $paths[$index] ?? null;

            if ($current !== null && $current !== $restored) {
                $imageStorage->delete($current);
            }

            if ($restored === null) {
                unset($services[$index]['image_path']);
            } else {
                $services[$index]['image_path'] = $restored;
            }
        }

        $content['services'] = $services;
        $site->content = $content;
        $site->save();
    }

    protected function generatedService(
        Site $site,
        Lead $lead,
        Team $currentTeam,
        LeadPhotoGenerator $images,
        mixed $index,
        string $prompt,
        bool $improve,
        SiteImageStorage $imageStorage,
    ): JsonResponse {
        if ($index === null) {
            throw ValidationException::withMessages(['index' => __('Choose which service image to update.')]);
        }

        $index = (int) $index;
        $services = array_values($site->content['services'] ?? []);

        if (! isset($services[$index]) || ! is_array($services[$index])) {
            throw ValidationException::withMessages(['index' => __('That service image slot is invalid.')]);
        }

        $service = $services[$index];
        $title = trim((string) ($service['title'] ?? 'Service'));
        $description = trim((string) ($service['description'] ?? ''));
        $existing = is_string($service['image_path'] ?? null) ? $service['image_path'] : null;

        if ($improve && ($existing === null || $existing === '')) {
            throw ValidationException::withMessages(['prompt' => __('Upload or generate an image before improving it.')]);
        }

        $planPrompt = $improve
            ? SiteImagePromptBuilder::forServiceImprove($lead, $title, $description, $prompt !== '' ? $prompt : null)
            : SiteImagePromptBuilder::forServiceCard($lead, $title, $description, $prompt !== '' ? $prompt : null);

        $plan = new SiteImageGenerationPlan(
            prompt: $planPrompt,
            aspectRatio: '4:3',
            placement: 'service_card',
            reasoning: $improve ? 'Sharpen and clean up a service card photo.' : 'Generate a service card marketing photo.',
            fromLlm: false,
        );

        $generated = $images->generate($plan, $currentTeam->id, $lead->id);
        $this->writeServiceImage($site, $index, $generated['path'], $imageStorage);

        return $this->media($site, $lead);
    }

    /**
     * @return array<string, string|null>
     */
    protected function placementUrls(Site $site, Lead $lead): array
    {
        $photos = $lead->photoUrls();
        $stored = is_array($site->content['images'] ?? null) ? $site->content['images'] : [];
        $urls = [];

        foreach (SiteImagePlacements::KEYS as $key) {
            $path = is_string($stored[$key] ?? null) ? $stored[$key] : null;
            $urls[$key] = $path !== null && $path !== ''
                ? app(SiteImageStorage::class)->url($path)
                : ($key === 'about_portrait' ? ($photos[1] ?? $photos[0] ?? null) : ($photos[0] ?? null));
        }

        return $urls;
    }

    protected function writePlacement(Site $site, string $slot, ?string $path, SiteImageStorage $imageStorage): void
    {
        $content = $site->content ?? [];
        $images = is_array($content['images'] ?? null) ? $content['images'] : [];
        $oldPath = is_string($images[$slot] ?? null) ? $images[$slot] : null;

        if ($oldPath !== null && $oldPath !== $path) {
            $imageStorage->forgetReplaced($oldPath);
        }

        if ($path === null) {
            unset($images[$slot]);
        } else {
            $images[$slot] = $path;
        }

        $content['images'] = $images;
        $site->content = $content;
        $site->save();
    }

    protected function generatedPlacement(
        Site $site,
        Lead $lead,
        Team $currentTeam,
        LeadPhotoGenerator $images,
        string $slot,
        string $prompt,
        bool $improve,
        SiteImageStorage $imageStorage,
    ): JsonResponse {
        $existing = $site->content['images'][$slot] ?? null;

        if ($improve && (! is_string($existing) || $existing === '')) {
            throw ValidationException::withMessages(['prompt' => __('Upload or generate an image before improving it.')]);
        }

        $planPrompt = $improve
            ? SiteImagePromptBuilder::forImprove($lead, $site, $prompt !== '' ? $prompt : null)
            : ($prompt !== '' ? $prompt : SiteImagePromptBuilder::forHero($lead, $site, null));

        $generated = $images->generate(new SiteImageGenerationPlan(
            prompt: $planPrompt,
            aspectRatio: '16:9',
            placement: $slot,
            reasoning: $improve ? 'Sharpen this page photo.' : 'Generate a photo for this page only.',
            fromLlm: false,
        ), $currentTeam->id, $lead->id);

        $this->writePlacement($site, $slot, $generated['path'], $imageStorage);

        return $this->media($site, $lead);
    }

    protected function slots(): array
    {
        return ['logo', 'photo', 'service', ...SiteImagePlacements::KEYS];
    }

    protected function maxIndexForSlot(string $slot): int
    {
        return $slot === 'service' ? self::MAX_SERVICE_INDEX : UpdateLeadProfileRequest::MAX_PHOTOS - 1;
    }
}

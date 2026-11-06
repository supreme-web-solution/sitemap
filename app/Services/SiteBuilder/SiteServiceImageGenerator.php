<?php

namespace App\Services\SiteBuilder;

use App\Data\SiteImageGenerationPlan;
use App\Models\Site;
use App\Services\Ai\AiProviderErrors;
use App\Services\Media\SiteImageStorage;
use Illuminate\Support\Str;

/**
 * Generate one AI photo per service card and store paths on site content.
 */
class SiteServiceImageGenerator
{
    public function __construct(
        protected LeadPhotoGenerator $images,
    ) {}

    /**
     * @return array{content: array<string, mixed>, message: string}
     */
    public function applyToContent(Site $site, array $content, string $userHint = ''): array
    {
        if (! $this->images->configured()) {
            throw new \RuntimeException(AiProviderErrors::serviceImagesNeedImageApiMessage());
        }

        $site->loadMissing('lead');
        $lead = $site->lead;

        if ($lead->team_id === null) {
            throw new \RuntimeException(__('This lead is missing a team.'));
        }

        $services = array_values($content['services'] ?? []);

        if ($services === []) {
            return [
                'content' => $content,
                'message' => __('There are no services on this site to illustrate.'),
            ];
        }

        $variants = SectionVariants::withDefaults($content['variants'] ?? []);

        if (! in_array($variants['services'] ?? '', ['featured', 'tiles', 'cards', 'stage', 'rail', 'columns', 'numbered', 'outline'], true)) {
            $variants['services'] = 'featured';
            $content['variants'] = SectionVariants::clean($variants);
        }

        $generated = 0;
        $teamId = (int) $lead->team_id;
        $leadId = (int) $lead->id;

        foreach ($services as $index => $service) {
            if (! is_array($service)) {
                continue;
            }

            $title = trim((string) ($service['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $description = trim((string) ($service['description'] ?? ''));
            $slug = Str::slug((string) ($service['slug'] ?? $title));

            if ($slug === '') {
                $slug = 'service-'.$index;
            }

            $plan = new SiteImageGenerationPlan(
                prompt: SiteImagePromptBuilder::forServiceCard($lead, $title, $description, $userHint),
                aspectRatio: '4:3',
                placement: 'service_card',
                reasoning: 'One distinct marketing photo per service card.',
                fromLlm: false,
            );

            try {
                $result = $this->images->generate($plan, $teamId, $leadId);
            } catch (\Throwable $exception) {
                SiteEditLog::info('service image skipped', [
                    'site_id' => $site->id,
                    'service' => $title,
                    'message' => Str::limit($exception->getMessage(), 200),
                ]);

                continue;
            }

            $oldPath = is_string($service['image_path'] ?? null) ? $service['image_path'] : null;

            if ($oldPath !== null) {
                app(SiteImageStorage::class)->forgetReplaced($oldPath);
            }

            $services[$index]['image_path'] = $result['path'];
            $generated++;
        }

        $content['services'] = $services;

        if ($generated === 0) {
            throw new \RuntimeException(__('Service photos could not be generated. Check your image API keys and try again.'));
        }

        return [
            'content' => $content,
            'message' => __('Generated :count unique service photos.', ['count' => $generated]),
        ];
    }
}

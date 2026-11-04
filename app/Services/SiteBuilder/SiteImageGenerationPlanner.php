<?php

namespace App\Services\SiteBuilder;

use App\Ai\Agents\SiteImagePlanner;
use App\Ai\Support\SiteImagePlanSubmission;
use App\Data\SiteImageGenerationPlan;
use App\Models\Lead;
use App\Models\Site;
use App\Services\Ai\AiAvailability;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SiteImageGenerationPlanner
{
    /**
     * @param  int|null  $photoIndex  Lead photo_paths index (0 = hero).
     */
    public function plan(
        Site $site,
        Lead $lead,
        string $userInstruction,
        ?int $photoIndex = null,
        array $attachmentPaths = [],
    ): SiteImageGenerationPlan {
        $site->loadMissing('lead');
        $index = $photoIndex ?? 0;
        $heroVariant = SectionVariants::clean($site->content['variants'] ?? [])['hero']
            ?? SectionVariants::CATALOG['hero']['default']
            ?? 'classic';

        $fallback = $this->heuristicPlan($lead, $site, $userInstruction, $index, $heroVariant);

        SiteEditLog::info('image generation plan', [
            'site_id' => $site->id,
            'from_llm' => false,
            'placement' => $fallback->placement,
            'aspect_ratio' => $fallback->aspectRatio,
        ]);

        return $fallback;
    }

    /**
     * Kept for rare cases that still need an LLM image brief. Chat edits use the heuristic plan.
     */
    public function planWithLlm(
        Site $site,
        Lead $lead,
        string $userInstruction,
        ?int $photoIndex = null,
        array $attachmentPaths = [],
    ): SiteImageGenerationPlan {
        $site->loadMissing('lead');
        $index = $photoIndex ?? 0;
        $heroVariant = SectionVariants::clean($site->content['variants'] ?? [])['hero']
            ?? SectionVariants::CATALOG['hero']['default']
            ?? 'classic';

        $context = $this->contextPrompt($site, $lead, $userInstruction, $index, $heroVariant, $attachmentPaths);

        if (AiAvailability::anthropicConfigured()) {
            $llm = $this->planWithAgent($context, $site, $index, $attachmentPaths);

            if ($llm !== null) {
                SiteEditLog::info('image generation plan', [
                    'site_id' => $site->id,
                    'from_llm' => true,
                    'placement' => $llm->placement,
                    'aspect_ratio' => $llm->aspectRatio,
                    'reasoning' => Str::limit($llm->reasoning, 200),
                ]);

                return $llm;
            }
        }

        $fallback = $this->heuristicPlan($lead, $site, $userInstruction, $index, $heroVariant);

        SiteEditLog::info('image generation plan', [
            'site_id' => $site->id,
            'from_llm' => false,
            'placement' => $fallback->placement,
            'aspect_ratio' => $fallback->aspectRatio,
        ]);

        return $fallback;
    }

    protected function contextPrompt(
        Site $site,
        Lead $lead,
        string $userInstruction,
        int $photoIndex,
        string $heroVariant,
        array $attachmentPaths = [],
    ): string {
        $heroLayouts = SectionVariants::promptList('hero');

        return implode("\n\n", array_filter([
            app(SiteEditConversation::class)->transcript($site),
            SiteChatAttachment::visionPromptPrefix($attachmentPaths),
            SiteDesignContextPack::manifest($site),
            'Business: '.$lead->name.' ('.($lead->category ?? 'local service').') in '.($lead->city ?? 'their city'),
            'Photo slot: lead photo index '.$photoIndex.($photoIndex === 0 ? ' (hero on this template)' : ' (gallery/supporting)'),
            'Hero layout preset: '.$heroVariant,
            'Hero layout options: '.$heroLayouts,
            SiteSectionDesignBlueprint::forTarget('hero'),
            'User request: '.trim($userInstruction),
            'Plan prompt + aspect_ratio + placement for this slot.',
        ]));
    }

    /**
     * @param  list<string>  $attachmentPaths
     */
    protected function planWithAgent(string $prompt, Site $site, int $photoIndex, array $attachmentPaths = []): ?SiteImageGenerationPlan
    {
        $submission = new SiteImagePlanSubmission;

        try {
            (new SiteImagePlanner($submission))->prompt(
                $prompt,
                SiteChatAttachment::imagesForPrompt($attachmentPaths),
            );
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Site image planner request failed.', ['site_id' => $site->id, 'message' => $exception->getMessage()]);

            return null;
        } catch (Throwable $exception) {
            Log::warning('Site image planner agent failed.', ['site_id' => $site->id, 'message' => $exception->getMessage()]);

            return null;
        }

        if ($submission->plan === null) {
            return null;
        }

        return $this->planFromPayload($submission->plan, true, $site, $photoIndex);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function planFromPayload(
        array $payload,
        bool $fromLlm,
        Site $site,
        int $photoIndex,
    ): ?SiteImageGenerationPlan {
        $prompt = trim((string) ($payload['prompt'] ?? ''));

        if ($prompt === '') {
            return null;
        }

        $heroVariant = SectionVariants::clean($site->content['variants'] ?? [])['hero']
            ?? SectionVariants::CATALOG['hero']['default']
            ?? 'classic';

        $fallbackRatio = SiteImageAspectRatio::defaultForPhotoIndex($photoIndex, $heroVariant);

        return new SiteImageGenerationPlan(
            prompt: Str::limit($prompt, 900, ''),
            aspectRatio: SiteImageAspectRatio::normalize($payload['aspect_ratio'] ?? null, $fallbackRatio),
            placement: trim((string) ($payload['placement'] ?? 'hero')) ?: 'hero',
            reasoning: trim((string) ($payload['reasoning'] ?? '')),
            fromLlm: $fromLlm,
        );
    }

    protected function heuristicPlan(
        Lead $lead,
        Site $site,
        string $userInstruction,
        int $photoIndex,
        string $heroVariant,
    ): SiteImageGenerationPlan {
        $aspectRatio = SiteImageAspectRatio::defaultForPhotoIndex($photoIndex, $heroVariant);
        $placement = $photoIndex === 0
            ? match ($heroVariant) {
                'split' => 'hero_side_column',
                'card' => 'hero_card',
                'overlay', 'photo-top' => 'hero_background',
                default => 'hero_background',
            }
        : 'gallery';

        return new SiteImageGenerationPlan(
            prompt: SiteImagePromptBuilder::forHero($lead, $site, $userInstruction),
            aspectRatio: $aspectRatio,
            placement: $placement,
            reasoning: 'Heuristic plan from hero layout preset '.$heroVariant.' and photo index '.$photoIndex.'.',
            fromLlm: false,
        );
    }
}

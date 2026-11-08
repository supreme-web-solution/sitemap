<?php

namespace App\Services\SiteBuilder;

use App\Ai\Agents\SiteEditExecutorAgent;
use App\Ai\Agents\SiteEditPlanner;
use App\Ai\Support\SiteEditExecutorState;
use App\Ai\Support\SiteEditPlanSubmission;
use App\Data\SiteEditPlan;
use App\Enums\SiteSection;
use App\Models\Site;
use App\Services\Ai\AiAvailability;
use App\Services\Ai\AiProviderErrors;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ask Anthropic to plan an edit, with a deterministic fallback when unavailable.
 */
class SiteEditOrchestrator
{
    /**
     * @param  list<array{region: string, selector: string, tag: string, text?: string|null, href?: string|null, action?: string|null}>  $previewEdits
     */
    public function plan(
        Site $site,
        string $instruction,
        ?string $designTarget,
        array $previewEdits,
        SiteRenderer $renderer,
        array $attachmentPaths = [],
    ): SiteEditPlan {
        $heuristic = SiteEditPlanIntentFilter::apply(
            $this->planHeuristic($instruction, $designTarget, $previewEdits, $site, $renderer),
            $instruction,
        );

        if ($this->shouldSkipLlm($heuristic, $instruction, $previewEdits, $attachmentPaths)) {
            SiteEditLog::info('orchestrator skipped llm', [
                'site_id' => $site->id,
                'reason' => 'Deterministic ops/presets cover this request.',
            ]);

            return $this->finalizePlan($heuristic, $site, $instruction);
        }

        if (AiAvailability::anthropicConfigured()) {
            try {
                $planned = $this->planWithLlm($site, $instruction, $designTarget, $previewEdits, $renderer, $attachmentPaths);

                if ($planned !== null) {
                    $planned = SiteEditPlanIntentFilter::apply($planned, $instruction);

                    return $this->finalizePlan($planned, $site, $instruction);
                }
            } catch (Throwable $exception) {
                SiteEditAiContext::record($exception);

                SiteEditLog::info('orchestrator llm failed', [
                    'site_id' => $site->id,
                    'message' => Str::limit($exception->getMessage(), 200),
                ]);
            }
        }

        return $this->finalizePlan($heuristic, $site, $instruction);
    }

    /**
     * @param  list<array{region: string, selector: string, tag: string, text?: string|null, href?: string|null, action?: string|null}>  $previewEdits
     * @param  list<string>  $attachmentPaths
     */
    protected function shouldSkipLlm(
        SiteEditPlan $heuristic,
        string $instruction,
        array $previewEdits,
        array $attachmentPaths,
    ): bool {
        if ($attachmentPaths !== [] || $previewEdits !== []) {
            return false;
        }

        if (SiteEditIntent::wantsServiceImages($instruction) || SiteEditIntent::wantsHeroPhotoBackground($instruction)) {
            return ! $heuristic->wantsCopyRevise();
        }

        if (app(SiteBlogIntent::class)->matches($instruction) || app(SiteServicesIntent::class)->matches($instruction)) {
            return ! $heuristic->wantsCopyRevise() && ! $heuristic->wantsDesignCss();
        }

        return $heuristic->documentOps !== [] && ! $heuristic->wantsCopyRevise() && ! $heuristic->wantsDesignCss();
    }

    protected function finalizePlan(SiteEditPlan $plan, Site $site, string $instruction): SiteEditPlan
    {
        if (! SiteEditAiContext::hadQuotaFailure()) {
            return $plan;
        }

        if (SiteEditIntent::canCompleteOffline($plan, $instruction, $site)) {
            return $plan;
        }

        $message = SiteEditIntent::wantsServiceImages($instruction) && ! app(LeadPhotoGenerator::class)->configured()
            ? AiProviderErrors::serviceImagesNeedImageApiMessage()
            : AiProviderErrors::siteEditUnavailableMessage();

        return $plan->withAiBlock($message);
    }

    /**
     * @param  list<array{region: string, selector: string, tag: string, text?: string|null, href?: string|null, action?: string|null}>  $previewEdits
     */
    protected function planWithLlm(
        Site $site,
        string $instruction,
        ?string $designTarget,
        array $previewEdits,
        SiteRenderer $renderer,
        array $attachmentPaths = [],
    ): ?SiteEditPlan {
        $prompt = implode("\n\n", array_filter([
            SiteChatContext::transcript($site),
            SiteChatAttachment::visionPromptPrefix($attachmentPaths),
            'User request: '.$instruction,
            $designTarget !== null ? 'Forced design target from preview click: '.$designTarget : null,
            $previewEdits !== [] ? PreviewEditPrompt::format($previewEdits, $instruction) : null,
            "Site facts:\n".SiteChatContext::facts($site),
            SiteTemplateContext::forSite($site),
            SiteDesignContextPack::manifest($site),
        ]));

        $executorState = new SiteEditExecutorState;

        if (config('sites.use_edit_executor', true)) {
            try {
                (new SiteEditExecutorAgent($executorState, $site))->prompt(
                    $prompt."\n\nExecute the request with tools, then complete_site_edit.",
                    SiteChatAttachment::imagesForPrompt($attachmentPaths),
                );
            } catch (Throwable $exception) {
                SiteEditAiContext::record($exception);

                SiteEditLog::info('executor agent failed', [
                    'site_id' => $site->id,
                    'message' => Str::limit($exception->getMessage(), 200),
                ]);
            }
        }

        if ($executorState->hasChanges()) {
            SiteEditLog::info('orchestrator executor plan', [
                'site_id' => $site->id,
                'document_ops' => count($executorState->documentOps),
                'design_css_targets' => $executorState->designCssTargets,
                'sections_add' => $executorState->sectionsAdd,
                'finished' => $executorState->finished,
            ]);

            return $this->normalizeExecutorPlan($executorState, $instruction, $designTarget, $previewEdits, $site, $renderer);
        }

        $submission = new SiteEditPlanSubmission;

        (new SiteEditPlanner($submission))->prompt(
            $prompt."\n\nEditable document:\n".SiteEditOpCatalog::plannerReference()."\n\nSubmit your plan JSON via the tool.",
            SiteChatAttachment::imagesForPrompt($attachmentPaths),
        );

        if (! is_array($submission->plan) || $submission->plan === []) {
            return null;
        }

        return $this->normalizePlan($submission->plan, $instruction, $designTarget, $previewEdits, $site, $renderer);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<array{region: string, selector: string, tag: string, text?: string|null, href?: string|null, action?: string|null}>  $previewEdits
     */
    protected function normalizeExecutorPlan(
        SiteEditExecutorState $state,
        string $instruction,
        ?string $designTarget,
        array $previewEdits,
        Site $site,
        SiteRenderer $renderer,
    ): SiteEditPlan {
        $plan = $state->toPlan(fromLlm: true);

        foreach ([$designTarget, ...PreviewEditPrompt::regions($previewEdits)] as $forced) {
            if (is_string($forced) && $forced !== '' && isset(SiteSectionCss::TARGETS[$forced])) {
                $targets = $plan->designCssTargets;
                $targets[] = $forced;
                $plan = new SiteEditPlan(
                    reasoning: $plan->reasoning,
                    copyInstruction: $plan->copyInstruction,
                    designCssTargets: array_values(array_unique($targets)),
                    designInstruction: $plan->designInstruction !== '' ? $plan->designInstruction : $instruction,
                    sectionsAdd: $plan->sectionsAdd,
                    sectionsRemove: $plan->sectionsRemove,
                    layoutVariants: $plan->layoutVariants,
                    documentOps: $plan->documentOps,
                    fromLlm: true,
                );
            }
        }

        $heuristicOps = SiteEditHeuristicOps::build($instruction, $site, $designTarget);

        if ($heuristicOps !== []) {
            $plan = new SiteEditPlan(
                reasoning: $plan->reasoning,
                copyInstruction: $plan->copyInstruction,
                designCssTargets: $plan->designCssTargets,
                designInstruction: $plan->designInstruction,
                sectionsAdd: $plan->sectionsAdd,
                sectionsRemove: $plan->sectionsRemove,
                layoutVariants: $plan->layoutVariants,
                documentOps: [...$plan->documentOps, ...$heuristicOps],
                fromLlm: true,
            );
            $plan = $plan->withoutRedundantTargetsAfterOps();
        }

        return $plan;
    }

    protected function normalizePlan(
        array $raw,
        string $instruction,
        ?string $designTarget,
        array $previewEdits,
        Site $site,
        SiteRenderer $renderer,
    ): SiteEditPlan {
        $designTargets = $this->stringList($raw['design_css_targets'] ?? []);

        foreach ([$designTarget, ...PreviewEditPrompt::regions($previewEdits)] as $forced) {
            if (is_string($forced) && $forced !== '' && isset(SiteSectionCss::TARGETS[$forced])) {
                $designTargets[] = $forced;
            }
        }

        $designTargets = array_values(array_unique($designTargets));

        $designInstruction = trim((string) ($raw['design_instruction'] ?? ''));

        if ($designInstruction === '' && $designTargets !== []) {
            $designInstruction = $instruction;
        }

        $documentOps = SiteEditOpCatalog::sanitize($this->documentOpsList($raw['document_ops'] ?? []));

        $plan = new SiteEditPlan(
            reasoning: trim((string) ($raw['reasoning'] ?? '')),
            copyInstruction: filled($raw['copy_instruction'] ?? null) ? trim((string) $raw['copy_instruction']) : null,
            designCssTargets: $designTargets,
            designInstruction: $designInstruction,
            sectionsAdd: $this->filterSectionKeys($this->stringList($raw['sections_add'] ?? [])),
            sectionsRemove: $this->filterSectionKeys($this->stringList($raw['sections_remove'] ?? [])),
            layoutVariants: $this->layoutVariantsMap($raw['layout_variants'] ?? []),
            documentOps: $documentOps,
            fromLlm: true,
        );

        $plan = $plan->withoutRedundantTargetsAfterOps();

        SiteEditLog::info('orchestrator plan', [
            'site_id' => $site->id,
            'from_llm' => true,
            'reasoning' => Str::limit($plan->reasoning, 400),
            'design_css_targets' => $plan->designCssTargets,
            'sections_add' => $plan->sectionsAdd,
            'sections_remove' => $plan->sectionsRemove,
            'layout_variants' => $plan->layoutVariants,
            'copy_instruction' => Str::limit((string) $plan->copyInstruction, 200),
        ]);

        return $plan;
    }

    /**
     * @param  list<array{region: string, selector: string, tag: string, text?: string|null, href?: string|null, action?: string|null}>  $previewEdits
     */
    protected function planHeuristic(
        string $instruction,
        ?string $designTarget,
        array $previewEdits,
        Site $site,
        SiteRenderer $renderer,
    ): SiteEditPlan {
        $designTargets = SiteChatInstruction::designTargets($instruction, $designTarget, $site, $renderer);

        if ($previewEdits !== []) {
            $designTargets = array_values(array_unique([
                ...$designTargets,
                ...PreviewEditPrompt::regions($previewEdits),
            ]));
        }

        $wantsDesign = $designTargets !== []
            && ! SiteChatInstruction::wantsLayoutPreset($instruction)
            && (
                $previewEdits !== []
                || $designTarget !== null
                || SiteChatInstruction::wantsSectionVisualChange($instruction)
            );

        $sectionsAdd = SiteStructuralIntent::wantsAddSection($instruction)
            ? SiteChatInstruction::mentionedSections($instruction)
            : [];

        $sectionsRemove = SiteStructuralIntent::wantsRemoveSection($instruction)
            ? SiteChatInstruction::mentionedSections($instruction)
            : [];

        $documentOps = SiteEditHeuristicOps::build($instruction, $site, $designTarget);

        $plan = new SiteEditPlan(
            reasoning: 'Heuristic routing (planner unavailable).',
            copyInstruction: $wantsDesign && $documentOps === []
                ? null
                : SiteChatInstruction::expand($instruction),
            designCssTargets: $wantsDesign ? $designTargets : [],
            designInstruction: $previewEdits !== []
                ? PreviewEditPrompt::format($previewEdits, $instruction)
                : $instruction,
            sectionsAdd: $sectionsAdd,
            sectionsRemove: $sectionsRemove,
            layoutVariants: SiteChatInstruction::wantsLayoutPreset($instruction) ? [] : [],
            documentOps: $documentOps,
            fromLlm: false,
        );

        $plan = $plan->withoutRedundantTargetsAfterOps();

        SiteEditLog::info('orchestrator plan', [
            'site_id' => $site->id,
            'from_llm' => false,
            'design_css_targets' => $plan->designCssTargets,
            'sections_add' => $plan->sectionsAdd,
        ]);

        return $plan;
    }

    /**
     * @return list<string>
     */
    protected function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => is_string($item) ? trim($item) : '',
            $value,
        )));
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    protected function filterSectionKeys(array $keys): array
    {
        return array_values(array_filter(
            $keys,
            fn (string $key) => SiteSection::tryFrom($key) !== null
                || CustomHomeSection::isKey($key)
                || isset(SiteSectionCss::TARGETS[$key]),
        ));
    }

    /**
     * @return array<string, string>
     */
    /**
     * @return list<array<string, mixed>>
     */
    protected function documentOpsList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, fn ($item) => is_array($item)));
    }

    protected function layoutVariantsMap(mixed $value): array
    {
        if ($value instanceof \stdClass) {
            $value = (array) $value;
        }

        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $section => $variant) {
            if (! is_string($section) || ! is_string($variant)) {
                continue;
            }

            if (! isset(SectionVariants::CATALOG[$section])) {
                continue;
            }

            if (! isset(SectionVariants::CATALOG[$section]['variants'][$variant])) {
                continue;
            }

            $map[$section] = $variant;
        }

        return $map;
    }
}

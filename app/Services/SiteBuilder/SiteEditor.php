<?php

namespace App\Services\SiteBuilder;

use App\Data\SiteDraft;
use App\Data\SiteEditPlan;
use App\Enums\SiteSection;
use App\Exceptions\SiteEditBlockedException;
use App\Models\Site;
use App\Services\Ai\AiProviderErrors;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SiteEditor
{
    public function __construct(
        protected SiteCopywriter $copywriter,
        protected RuleBasedCopywriter $layout,
        protected SiteSectionDesignAssistant $sectionDesign,
        protected SiteRenderer $renderer,
        protected SiteEditOrchestrator $orchestrator,
        protected SiteDocumentOpsApplier $documentOps,
        protected SiteServiceImageGenerator $serviceImages,
    ) {}

    /**
     * @param  list<array{region: string, selector: string, tag: string, text?: string|null, href?: string|null, action?: string|null}>  $previewEdits
     */
    public function apply(
        Site $site,
        string $instruction,
        ?string $designTarget = null,
        array $previewEdits = [],
        ?SiteEditProgress $progress = null,
        array $attachmentPaths = [],
        ?string $userInstruction = null,
    ): SiteDraft {
        $site->loadMissing('lead');

        $instruction = trim($instruction);
        $intentSource = trim(trim((string) $userInstruction)."\n".$instruction);
        $previewEdits = self::normalizePreviewEdits($previewEdits);

        SiteEditLog::info('edit started', [
            'site_id' => $site->id,
            'lead_id' => $site->lead_id,
            'instruction' => $instruction,
            'design_target' => $designTarget,
            'preview_edit_count' => count($previewEdits),
        ]);

        $progress?->line(__('Reading your request and preview targets'));

        $plan = $this->orchestrator->plan($site, $instruction, $designTarget, $previewEdits, $this->renderer, $attachmentPaths);

        $plan = SiteEditPlanIntentFilter::apply($plan, $intentSource);

        if ($plan->aiProviderUnavailable) {
            throw new SiteEditBlockedException(
                $plan->aiProviderMessage ?? AiProviderErrors::siteEditUnavailableMessage(),
            );
        }

        SiteEditLog::info('edit routing', [
            'site_id' => $site->id,
            'from_llm' => $plan->fromLlm,
            'design_css_targets' => $plan->designCssTargets,
            'document_ops' => count($plan->documentOps),
            'planner_instruction' => Str::limit((string) ($plan->copyInstruction ?? $plan->designInstruction), 500),
        ]);

        $progress?->line(__('Planning the edit'));

        $current = new SiteDraft($site->template, $site->content);
        $draft = $current;
        $workingTemplate = $site->template;

        if ($plan->wantsDocumentOps()) {
            $progress?->line(__('Applying direct changes to your site'));

            try {
                $applied = $this->documentOps->apply($site, $plan->documentOps);
                $workingTemplate = $applied['template'];
                $draft = new SiteDraft($workingTemplate, $applied['content']);

                SiteEditLog::info('document ops applied', [
                    'site_id' => $site->id,
                    'op_count' => count($plan->documentOps),
                ]);
            } catch (ValidationException $exception) {
                SiteEditLog::info('document ops rejected', [
                    'site_id' => $site->id,
                    'message' => Str::limit($exception->getMessage(), 200),
                ]);
            }
        }

        if ($plan->wantsCopyRevise()) {
            $progress?->line(__('Updating site copy plan'));

            if ($this->copywriter instanceof AiSitePlanner && SiteEditAiContext::hadQuotaFailure()) {
                $draft = $this->layout->revise($site->lead, $draft, (string) $plan->copyInstruction);
            } elseif ($this->copywriter instanceof AiSitePlanner) {
                $draft = $this->copywriter->reviseForSite($site->lead, $draft, (string) $plan->copyInstruction, $site);
            } else {
                $draft = $this->copywriter->revise($site->lead, $draft, (string) $plan->copyInstruction);
            }
        }

        $content = $draft->content;
        $content = $this->applySectionOps($content, $plan);

        if ($plan->wantsDesignCss()) {
            $savedVariants = SectionVariants::withDefaults($content['variants'] ?? []);
            $content['variants'] = $savedVariants;
        }

        $sectionCss = SiteSectionCss::clean($content['section_css'] ?? null);

        if ($plan->wantsDesignCss()) {
            if (! $this->sectionDesign->configured()) {
                throw new RuntimeException(__('AI design code needs ANTHROPIC_API_KEY in your environment. Layout presets are disabled for design chat; add your API key to generate real CSS.'));
            }

            $designInstruction = $plan->designInstruction !== ''
                ? $plan->designInstruction
                : $instruction;

            $editsForTarget = fn (string $target) => array_values(array_filter(
                $previewEdits,
                fn (array $edit) => ($edit['region'] ?? '') === $target,
            ));

            foreach ($plan->designCssTargets as $target) {
                $progress?->line(__('Writing scoped CSS for :section', [
                    'section' => SiteSectionCss::TARGETS[$target]['label'] ?? $target,
                ]));

                try {
                    $patch = $this->sectionDesign->generate(
                        $site,
                        $target,
                        $designInstruction,
                        $sectionCss[$target] ?? null,
                        $editsForTarget($target),
                        $attachmentPaths,
                    );
                } catch (RuntimeException $exception) {
                    SiteEditAiContext::record($exception);

                    SiteEditLog::info('design css skipped', [
                        'site_id' => $site->id,
                        'target' => $target,
                        'message' => Str::limit($exception->getMessage(), 200),
                    ]);

                    continue;
                }

                $sectionCss[$target] = $patch['css'];

                SiteEditLog::info('design css applied', [
                    'site_id' => $site->id,
                    'target' => $target,
                    'summary' => Str::limit($patch['summary'] ?? '', 200),
                ]);
            }
        }

        if ($plan->layoutVariants !== []) {
            $variants = SectionVariants::withDefaults($content['variants'] ?? []);

            foreach ($plan->layoutVariants as $section => $variant) {
                $variants[$section] = $variant;
            }

            $content['variants'] = SectionVariants::clean($variants);
        } elseif (SiteChatInstruction::wantsLayoutPreset($instruction) && ! $plan->wantsDesignCss()) {
            $content['variants'] = $this->layout->applyLayoutVariants(
                SectionVariants::withDefaults($site->content['variants'] ?? []),
                $instruction,
                $site->content['variants'] ?? [],
                $instruction,
            );
        }

        $content['section_css'] = $sectionCss;

        $progress?->line(__('Checking your edit against your request'));

        $final = SiteContentSchema::preserveEditorFields($site->content, $content);

        $outcomeIssues = SiteEditResultVerifier::issues($instruction, $site->content, $final, $plan, $designTarget);

        if ($outcomeIssues !== [] && $this->sectionDesign->configured()) {
            SiteEditLog::info('edit outcome verification failed; recovering', [
                'site_id' => $site->id,
                'issues' => $outcomeIssues,
            ]);

            $recovery = SiteEditResultVerifier::recoveryPlan($plan, $instruction, $outcomeIssues);

            if ($recovery->wantsDesignCss()) {
                $progress?->line(__('Adjusting the edit to match your request'));

                $sectionCss = SiteSectionCss::clean($final['section_css'] ?? null);
                $designInstruction = $recovery->designInstruction;

                foreach ($recovery->designCssTargets as $target) {
                    if (! isset(SiteSectionCss::TARGETS[$target])) {
                        continue;
                    }

                    $progress?->line(__('Writing scoped CSS for :section', [
                        'section' => SiteSectionCss::TARGETS[$target]['label'] ?? $target,
                    ]));

                    $patch = $this->sectionDesign->generate(
                        $site,
                        $target,
                        $designInstruction,
                        $sectionCss[$target] ?? null,
                        array_values(array_filter(
                            $previewEdits,
                            fn (array $edit) => ($edit['region'] ?? '') === $target,
                        )),
                        $attachmentPaths,
                    );

                    $sectionCss[$target] = $patch['css'];
                }

                $final['section_css'] = $sectionCss;
                $final = SiteContentSchema::preserveEditorFields($site->content, $final);
            }
        }

        $final = SiteEditHeroEnsurer::apply($final, $intentSource);

        if (SiteEditIntent::wantsServiceImages($intentSource)) {
            $progress?->line(__('Generating service photos'));

            try {
                $serviceResult = $this->serviceImages->applyToContent($site, $final, $intentSource);
                $final = $serviceResult['content'];

                SiteEditLog::info('service images generated', [
                    'site_id' => $site->id,
                    'message' => $serviceResult['message'],
                ]);
            } catch (RuntimeException $exception) {
                if (! self::contentChanged($site->content, $final)) {
                    throw $exception;
                }

                SiteEditLog::info('service images incomplete', [
                    'site_id' => $site->id,
                    'message' => Str::limit($exception->getMessage(), 200),
                ]);
            }
        }

        $progress?->line(__('Refreshing preview'));

        SiteEditLog::info('edit finished', [
            'site_id' => $site->id,
            'sections_before' => array_values($site->content['sections'] ?? []),
            'sections_after' => array_values($final['sections'] ?? []),
            'headline_changed' => ($site->content['headline'] ?? '') !== ($final['headline'] ?? ''),
        ]);

        return new SiteDraft(
            $draft->template,
            $final,
        );
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function applySectionOps(array $content, SiteEditPlan $plan): array
    {
        $sections = array_values($content['sections'] ?? SiteSection::defaultOrder());

        foreach ($plan->sectionsAdd as $key) {
            $sections = SiteStructuralIntent::insertSection($sections, $key);
        }

        foreach ($plan->sectionsRemove as $key) {
            $sections = array_values(array_filter($sections, fn (string $section) => $section !== $key));
        }

        if ($plan->sectionsAdd !== [] || $plan->sectionsRemove !== []) {
            SiteEditLog::info('sections updated from plan', [
                'sections_add' => $plan->sectionsAdd,
                'sections_remove' => $plan->sectionsRemove,
                'sections_after' => $sections,
            ]);
        }

        $content['sections'] = $sections;

        return $content;
    }

    /**
     * @param  list<array<string, mixed>>  $previewEdits
     * @return list<array{region: string, selector: string, tag: string, text?: string|null, href?: string|null, action?: string|null}>
     */
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    protected static function contentChanged(array $before, array $after): bool
    {
        return json_encode($before) !== json_encode($after);
    }

    /**
     * @param  list<array<string, mixed>>  $previewEdits
     * @return list<array{region: string, selector: string, tag: string, text?: string|null, href?: string|null, action?: string|null}>
     */
    protected static function normalizePreviewEdits(array $previewEdits): array
    {
        $clean = [];

        foreach ($previewEdits as $edit) {
            if (! is_array($edit)) {
                continue;
            }

            $region = (string) ($edit['region'] ?? '');

            if (! isset(SiteSectionCss::TARGETS[$region])) {
                continue;
            }

            $clean[] = [
                'region' => $region,
                'selector' => (string) ($edit['selector'] ?? ''),
                'tag' => (string) ($edit['tag'] ?? 'element'),
                'text' => filled($edit['text'] ?? null) ? (string) $edit['text'] : null,
                'href' => filled($edit['href'] ?? null) ? (string) $edit['href'] : null,
                'action' => filled($edit['action'] ?? null) ? (string) $edit['action'] : null,
            ];
        }

        return $clean;
    }
}

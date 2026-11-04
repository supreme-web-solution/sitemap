<?php

namespace App\Services\SiteBuilder;

use App\Data\SiteEditPlan;
use App\Enums\SiteSection;

/**
 * Validates any AI edit outcome (structure, presets, CSS, copy) against the user request.
 */
class SiteEditResultVerifier
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    public static function issues(
        string $instruction,
        array $before,
        array $after,
        SiteEditPlan $plan,
        ?string $designTarget = null,
    ): array {
        $issues = [];

        $issues = array_merge($issues, self::structuralIssues($instruction, $before, $after, $plan));
        $issues = array_merge($issues, self::plannedCssIssues($before, $after, $plan));
        $issues = array_merge($issues, self::alignmentIssues($instruction, $after, $plan, $designTarget));

        return array_values(array_unique($issues));
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    protected static function structuralIssues(
        string $instruction,
        array $before,
        array $after,
        SiteEditPlan $plan,
    ): array {
        $issues = [];
        $beforeSections = array_values($before['sections'] ?? []);
        $afterSections = array_values($after['sections'] ?? []);

        foreach ($plan->sectionsAdd as $key) {
            if (! in_array($key, $afterSections, true)) {
                $label = SiteSection::tryFrom($key)?->label() ?? $key;
                $issues[] = __('The plan was to add :section but it is still missing from the homepage.', ['section' => $label]);
            }
        }

        foreach ($plan->sectionsRemove as $key) {
            if (in_array($key, $afterSections, true)) {
                $label = SiteSection::tryFrom($key)?->label() ?? $key;
                $issues[] = __('The plan was to remove :section but it is still on the homepage.', ['section' => $label]);
            }
        }

        if (SiteStructuralIntent::wantsAddSection($instruction)) {
            foreach (SiteChatInstruction::mentionedSections($instruction) as $key) {
                if (! in_array($key, $afterSections, true)) {
                    $label = SiteSection::tryFrom($key)?->label() ?? $key;
                    $issues[] = __('You asked to add :section but it is not on the homepage yet.', ['section' => $label]);
                }
            }
        }

        if ($beforeSections === $afterSections && $plan->sectionsAdd === [] && $plan->sectionsRemove === []) {
            return $issues;
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    protected static function plannedCssIssues(array $before, array $after, SiteEditPlan $plan): array
    {
        $issues = [];
        $beforeCss = SiteSectionCss::clean($before['section_css'] ?? null);
        $afterCss = SiteSectionCss::clean($after['section_css'] ?? null);

        foreach ($plan->designCssTargets as $target) {
            $new = $afterCss[$target] ?? '';

            if ($new === '') {
                $label = SiteSectionCss::TARGETS[$target]['label'] ?? $target;
                $issues[] = __('Expected custom CSS for :section but none was applied.', ['section' => $label]);
            }
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    protected static function alignmentIssues(
        string $instruction,
        array $after,
        SiteEditPlan $plan,
        ?string $designTarget,
    ): array {
        if (! SiteSectionCssIntentChecker::instructionWantsHorizontalCenter($instruction)
            && ! SiteSectionCssIntentChecker::instructionWantsHorizontalLeft($instruction)
            && ! SiteSectionCssIntentChecker::instructionWantsHorizontalRight($instruction)
            && ! SiteSectionCssIntentChecker::instructionWantsFlexAlignment($instruction)) {
            return [];
        }

        $issues = [];
        $afterCss = SiteSectionCss::clean($after['section_css'] ?? null);
        $variants = SectionVariants::clean($after['variants'] ?? null);

        foreach (self::relevantSectionKeys($instruction, $designTarget, $plan) as $target) {
            $css = $afterCss[$target] ?? '';

            if ($css !== '') {
                $issues = array_merge($issues, SiteSectionCssIntentChecker::issues($instruction, $css, $target));

                continue;
            }

            if (self::layoutPresetSatisfiesAlignment($instruction, $target, $variants[$target] ?? null)) {
                continue;
            }

            $label = SiteSectionCss::TARGETS[$target]['label'] ?? $target;
            $issues[] = __('You asked for a :section layout change but only a preset was switched — scoped CSS is still needed to match your alignment request.', [
                'section' => $label,
            ]);
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    protected static function relevantSectionKeys(
        string $instruction,
        ?string $designTarget,
        SiteEditPlan $plan,
    ): array {
        $keys = [];

        if ($designTarget !== null && isset(SiteSectionCss::TARGETS[$designTarget])) {
            $keys[] = $designTarget;
        }

        foreach ($plan->designCssTargets as $target) {
            $keys[] = $target;
        }

        $mentioned = SiteChatInstruction::mentionedSections($instruction);

        foreach (array_keys(SiteSectionCss::TARGETS) as $target) {
            if (in_array($target, $mentioned, true)) {
                $keys[] = $target;
            }
        }

        if ($keys === [] && SiteSectionCssIntentChecker::instructionWantsHorizontalCenter($instruction)) {
            foreach (array_keys($plan->layoutVariants) as $section) {
                if (isset(SiteSectionCss::TARGETS[$section])) {
                    $keys[] = $section;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  array<string, string>  $variants
     */
    protected static function layoutPresetSatisfiesAlignment(string $instruction, string $target, ?string $variant): bool
    {
        if ($variant === null) {
            return false;
        }

        if (SiteSectionCssIntentChecker::instructionWantsHorizontalCenter($instruction)) {
            return match ($target) {
                'hero' => $variant === 'centered',
                default => false,
            };
        }

        return false;
    }

    /**
     * @param  list<string>  $issues
     */
    public static function recoveryPlan(SiteEditPlan $plan, string $instruction, array $issues): SiteEditPlan
    {
        $targets = $plan->designCssTargets;
        $designInstruction = $plan->designInstruction !== '' ? $plan->designInstruction : $instruction;

        foreach ($issues as $issue) {
            foreach (array_keys(SiteSectionCss::TARGETS) as $key) {
                $label = SiteSectionCss::TARGETS[$key]['label'];

                if (str_contains($issue, $label) && ! in_array($key, $targets, true)) {
                    $targets[] = $key;
                }
            }
        }

        $designInstruction .= "\n\nFix these gaps before finishing:\n- ".implode("\n- ", $issues);

        return new SiteEditPlan(
            reasoning: $plan->reasoning,
            copyInstruction: $plan->copyInstruction,
            designCssTargets: array_values(array_unique($targets)),
            designInstruction: trim($designInstruction),
            sectionsAdd: $plan->sectionsAdd,
            sectionsRemove: $plan->sectionsRemove,
            layoutVariants: $plan->layoutVariants,
            documentOps: $plan->documentOps,
            fromLlm: $plan->fromLlm,
        );
    }
}

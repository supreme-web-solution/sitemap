<?php

namespace App\Services\SiteBuilder;

use App\Ai\Agents\SiteSectionDesignCritic;
use App\Ai\Support\SectionDesignReviewSubmission;
use App\Models\Site;
use App\Services\Ai\AiAvailability;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * LLM cross-check after CSS generation; complements deterministic intent checks.
 */
class SiteSectionDesignReviewer
{
    public function configured(): bool
    {
        return AiAvailability::anthropicConfigured();
    }

    /**
     * @return array{passed: bool, feedback: string, revision_brief: string}
     */
    public function review(
        Site $site,
        string $target,
        string $userInstruction,
        string $css,
        string $summary,
    ): array {
        if (! $this->configured()) {
            return ['passed' => true, 'feedback' => '', 'revision_brief' => ''];
        }

        if (self::deterministicAlignmentSatisfied($userInstruction, $css, $target)) {
            return ['passed' => true, 'feedback' => '', 'revision_brief' => ''];
        }

        $definition = SiteSectionCss::TARGETS[$target];
        $submission = new SectionDesignReviewSubmission;

        $variantNote = '';

        if (isset(SectionVariants::CATALOG[$target])) {
            $preset = $site->content['variants'][$target] ?? SectionVariants::CATALOG[$target]['default'];
            $presetLabel = SectionVariants::CATALOG[$target]['variants'][$preset] ?? $preset;
            $variantNote = 'Layout preset for this section: '.$presetLabel.' ('.$preset.')';
        }

        $prompt = implode("\n\n", array_filter([
            'User request: '.$userInstruction,
            'Section: '.$definition['label'].' ('.$target.')',
            'Allowed selectors: '.implode(', ', $definition['scope']),
            $variantNote !== '' ? $variantNote : null,
            'Designer summary: '.$summary,
            'Markup reference:',
            SiteSectionDesignBlueprint::forTarget($target),
            'Submitted CSS patch:',
            $css,
            'Does this CSS fulfill the user request for this section? Submit your review.',
        ]));

        try {
            (new SiteSectionDesignCritic($submission))->prompt($prompt);
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Section design critic request failed.', [
                'site_id' => $site->id,
                'target' => $target,
                'message' => $exception->getMessage(),
            ]);

            return ['passed' => true, 'feedback' => '', 'revision_brief' => ''];
        } catch (Throwable $exception) {
            Log::warning('Section design critic agent failed.', [
                'site_id' => $site->id,
                'target' => $target,
                'message' => $exception->getMessage(),
            ]);

            return ['passed' => true, 'feedback' => '', 'revision_brief' => ''];
        }

        if ($submission->payload === null) {
            return ['passed' => true, 'feedback' => '', 'revision_brief' => ''];
        }

        return $submission->payload;
    }

    protected static function deterministicAlignmentSatisfied(string $userInstruction, string $css, string $target): bool
    {
        $wantsAlignment = SiteSectionCssIntentChecker::instructionWantsHorizontalCenter($userInstruction)
            || SiteSectionCssIntentChecker::instructionWantsHorizontalLeft($userInstruction)
            || SiteSectionCssIntentChecker::instructionWantsHorizontalRight($userInstruction)
            || SiteSectionCssIntentChecker::instructionWantsFlexAlignment($userInstruction);

        if (! $wantsAlignment) {
            return false;
        }

        return SiteSectionCssIntentChecker::issues($userInstruction, $css, $target) === [];
    }
}

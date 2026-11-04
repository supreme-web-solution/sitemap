<?php

namespace App\Services\SiteBuilder;

use App\Ai\Agents\SiteSectionDesigner;
use App\Ai\Support\SectionCssSubmission;
use App\Models\Site;
use App\Services\Ai\AiAvailability;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Ask the Laravel AI design agent to write scoped CSS for one section.
 */
class SiteSectionDesignAssistant
{
    private const MAX_ATTEMPTS = 2;

    public function __construct(protected SiteSectionDesignReviewer $reviewer) {}

    public function configured(): bool
    {
        return AiAvailability::anthropicConfigured();
    }

    /**
     * @param  list<array{region: string, selector: string, tag: string, text?: string|null, href?: string|null, action?: string|null}>  $previewEdits
     * @return array{css: string, summary: string}
     */
    public function generate(
        Site $site,
        string $target,
        string $userInstruction,
        ?string $currentCss = null,
        array $previewEdits = [],
        array $attachmentPaths = [],
    ): array {
        if (! isset(SiteSectionCss::TARGETS[$target])) {
            throw new RuntimeException(__('Unknown section for design.'));
        }

        if (! $this->configured()) {
            throw new RuntimeException(__('AI design editing needs an Anthropic API key configured.'));
        }

        $definition = SiteSectionCss::TARGETS[$target];

        $preset = SiteSectionCssPresets::match($target, $userInstruction);

        if ($preset !== null && SiteSectionCss::validate($target, $preset['css']) === []) {
            SiteEditLog::info('design css preset applied', [
                'site_id' => $site->id,
                'target' => $target,
                'summary' => Str::limit($preset['summary'], 200),
            ]);

            return [
                'css' => $preset['css'],
                'summary' => $preset['summary'],
            ];
        }

        $errors = [];

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $prompt = SiteDesignContextPack::userPrompt($site, $target, $userInstruction, $currentCss, $previewEdits);

            if ($attachmentPaths !== []) {
                $prefix = SiteChatAttachment::visionPromptPrefix($attachmentPaths);

                if ($prefix !== '') {
                    $prompt = $prefix."\n\n".$prompt;
                }
            }

            if ($errors !== []) {
                $prompt .= "\n\nPrevious attempt failed:\n- ".implode("\n- ", $errors)."\nFix and submit valid scoped CSS via the tool.";
            }

            $submission = new SectionCssSubmission;

            try {
                (new SiteSectionDesigner($submission, $target))->prompt(
                    $prompt,
                    SiteChatAttachment::imagesForPrompt($attachmentPaths),
                );
            } catch (ConnectionException|RequestException $exception) {
                SiteEditAiContext::record($exception);

                Log::warning('Section design AI request failed.', ['site_id' => $site->id, 'target' => $target, 'message' => $exception->getMessage()]);

                throw new RuntimeException(__('The design assistant could not respond. Try again.'));
            } catch (Throwable $exception) {
                Log::warning('Section design AI agent failed.', ['site_id' => $site->id, 'target' => $target, 'message' => $exception->getMessage()]);

                throw new RuntimeException(__('The design assistant could not respond. Try again.'));
            }

            if ($submission->payload !== null) {
                $css = $submission->payload['css'];
                $summary = $submission->payload['summary'] !== ''
                    ? $submission->payload['summary']
                    : __('Updated the :section design.', ['section' => $definition['label']]);

                $intentIssues = SiteSectionCssIntentChecker::issues($userInstruction, $css, $target);

                if ($intentIssues !== []) {
                    $errors = $intentIssues;
                    Log::info('Section design intent check failed; retrying.', [
                        'site_id' => $site->id,
                        'target' => $target,
                        'attempt' => $attempt,
                        'issues' => $intentIssues,
                    ]);

                    continue;
                }

                $review = $this->reviewer->review($site, $target, $userInstruction, $css, $summary);

                if (! $review['passed']) {
                    $errors = array_filter([
                        $review['feedback'] !== '' ? $review['feedback'] : null,
                        $review['revision_brief'] !== '' ? 'Revision: '.$review['revision_brief'] : null,
                    ]);
                    Log::info('Section design critic rejected patch; retrying.', [
                        'site_id' => $site->id,
                        'target' => $target,
                        'attempt' => $attempt,
                        'feedback' => $review['feedback'],
                    ]);

                    continue;
                }

                return [
                    'css' => $css,
                    'summary' => $summary,
                ];
            }

            $errors = ['The model did not submit valid scoped CSS.'];

            Log::info('Section design agent finished without accepted CSS; retrying.', ['site_id' => $site->id, 'target' => $target, 'attempt' => $attempt]);
        }

        throw new RuntimeException(__('The generated CSS was not safe or scoped correctly. Try rephrasing your request.'));
    }
}

<?php

namespace App\Services\SiteBuilder;

use App\Ai\Agents\SiteEditTurnAgent;
use App\Ai\Support\SiteEditTurnSubmission;
use App\Data\SiteEditTurnDecision;
use App\Models\Site;
use App\Services\Ai\AiAvailability;
use App\Services\Ai\AiProviderErrors;
use Illuminate\Support\Str;
use Throwable;

class SiteEditTurnResolver
{
    public function __construct(protected SiteEditConversation $conversation) {}

    /**
     * @param  list<string>  $attachmentPaths
     */
    public function resolve(Site $site, string $instruction, array $attachmentPaths = []): SiteEditTurnDecision
    {
        try {
            $decision = $this->resolveWithLlm($site, $instruction, $attachmentPaths);

            if ($decision !== null) {
                return $decision;
            }
        } catch (Throwable $exception) {
            SiteEditAiContext::record($exception);

            SiteEditLog::info('turn agent failed', [
                'site_id' => $site->id,
                'message' => Str::limit($exception->getMessage(), 200),
            ]);
        }

        if (SiteEditAiContext::hadQuotaFailure()) {
            return new SiteEditTurnDecision(
                message: AiProviderErrors::siteEditUnavailableMessage(),
                applyInstruction: null,
                generateHeroImage: false,
                imageHint: null,
                fromLlm: false,
            );
        }

        if (AiAvailability::anthropicConfigured()) {
            return $this->defaultApplyDecision($instruction);
        }

        return $this->resolveWithoutLlm($site, $instruction);
    }

    /**
     * @param  list<string>  $attachmentPaths
     */
    protected function resolveWithLlm(Site $site, string $instruction, array $attachmentPaths = []): ?SiteEditTurnDecision
    {
        $submission = new SiteEditTurnSubmission;

        $agent = new SiteEditTurnAgent($submission);
        $agent->continueOrStart($site->ai_conversation_id, $site);

        $prompt = implode("\n\n", array_filter([
            SiteChatContext::transcript($site),
            "Site facts:\n".SiteChatContext::facts($site),
            SiteChatContext::turnContext($instruction, $attachmentPaths),
            'Submit turn_json via the tool.',
        ]));

        $agent->prompt($prompt, SiteChatAttachment::imagesForPrompt($attachmentPaths));

        $this->conversation->persistConversationId($site, $agent->currentConversation());

        $turn = $submission->turn;

        if (! is_array($turn) || $turn === []) {
            return null;
        }

        return $this->decisionFromTurnArray($site, $instruction, $turn, fromLlm: true);
    }

    /**
     * @param  array<string, mixed>  $turn
     */
    protected function decisionFromTurnArray(Site $site, string $instruction, array $turn, bool $fromLlm): ?SiteEditTurnDecision
    {
        $mode = (string) ($turn['mode'] ?? 'apply_edit');
        $message = trim((string) ($turn['message'] ?? ''));
        $applyInstruction = filled($turn['apply_instruction'] ?? null)
            ? trim((string) $turn['apply_instruction'])
            : null;
        $imageHint = filled($turn['image_hint'] ?? null) ? trim((string) $turn['image_hint']) : null;

        $useAttached = in_array($mode, ['apply_attached_image', 'apply_attached_image_and_edit'], true);
        $generateHero = in_array($mode, ['generate_hero_image', 'apply_and_generate_hero_image'], true)
            || SiteEditIntent::wantsHeroPhotoBackground($instruction);
        $wantsSiteWork = SiteEditIntent::wantsSiteWorkBeyondHeroPhoto($instruction)
            || filled($applyInstruction);
        $apply = in_array($mode, ['apply_edit', 'apply_and_generate_hero_image', 'apply_attached_image_and_edit'], true)
            || $wantsSiteWork;
        $placement = SiteAttachedImagePlacer::normalizePlacement(
            (string) ($turn['attached_image_placement'] ?? SiteAttachedImagePlacer::PLACEMENT_HERO),
        );

        if ($message === '' && ! $generateHero && ! $apply && ! $useAttached) {
            return null;
        }

        SiteEditLog::info('turn decision', [
            'site_id' => $site->id,
            'mode' => $mode,
            'message' => Str::limit($message, 400),
            'apply_instruction' => Str::limit((string) $applyInstruction, 200),
            'generate_hero_image' => $generateHero,
            'apply_attached_image' => $useAttached,
            'attached_image_placement' => $placement,
            'image_hint' => Str::limit((string) $imageHint, 200),
            'from_llm' => $fromLlm,
        ]);

        return new SiteEditTurnDecision(
            message: $message,
            applyInstruction: $apply ? ($applyInstruction ?? $instruction) : null,
            generateHeroImage: $generateHero,
            imageHint: $imageHint,
            fromLlm: $fromLlm,
            applyAttachedImage: $useAttached,
            attachedImagePlacement: $placement,
        );
    }

    /**
     * When Anthropic is configured but the turn agent did not submit JSON — still run the edit pipeline.
     */
    protected function defaultApplyDecision(string $instruction): SiteEditTurnDecision
    {
        return new SiteEditTurnDecision(
            message: '',
            applyInstruction: $instruction,
            generateHeroImage: false,
            imageHint: null,
            fromLlm: false,
        );
    }

    /**
     * Offline fallback only (no API key). No intent classifiers — apply edits or answer with site facts.
     */
    protected function resolveWithoutLlm(Site $site, string $instruction): SiteEditTurnDecision
    {
        if (str_ends_with(trim($instruction), '?')) {
            return new SiteEditTurnDecision(
                message: __('Based on the current site:')."\n\n".SiteChatContext::facts($site),
                applyInstruction: null,
                generateHeroImage: false,
                imageHint: null,
                fromLlm: false,
            );
        }

        return $this->defaultApplyDecision($instruction);
    }
}

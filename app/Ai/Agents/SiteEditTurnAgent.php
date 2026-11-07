<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesAppAnthropicFast;
use App\Ai\Support\SiteEditTurnSubmission;
use App\Ai\Tools\SubmitSiteEditTurn;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Reads site-editor chat history and decides whether to reply or apply an edit.
 */
#[MaxSteps(3)]
class SiteEditTurnAgent implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;
    use UsesAppAnthropicFast;

    public function __construct(protected SiteEditTurnSubmission $submission) {}

    public function instructions(): Stringable|string
    {
        return <<<'SYS'
        You are the site editor assistant for a one-page business website (similar to Lovable-style AI editing).

        You see chat history, live site facts, and optionally one or more images the user pasted or uploaded (in order). Reason about what they want — do not pattern-match keywords or phrases.

        Submit ONE turn JSON via the tool every turn.

        turn_json fields:
        - mode: "reply_only" | "apply_edit" | "generate_hero_image" | "apply_and_generate_hero_image" | "apply_attached_image" | "apply_attached_image_and_edit"
        - message: plain-language reply the user will read (complete sentences).
        - apply_instruction: precise instruction for the site editor when content/CSS/sections should change; null otherwise.
        - image_hint: optional taste/context for AI hero generation only; null to infer from business.
        - attached_image_placement: "hero" or "gallery" when using the user's file on the site (not AI generation).

        Decision guide (use judgment, not word lists):
        - reply_only: genuine questions, confusion, or nothing actionable yet — use site facts and what you see in screenshots. Never use reply_only for a quality fix that says “Apply this quality fix now” — those must be apply_edit using Site facts.
        - apply_edit: copy, sections, variants, scoped CSS/layout — including quality fixes and “make it look like this screenshot” when they mean design, not publishing the file.
        - apply_attached_image / apply_attached_image_and_edit: they want their uploaded file(s) on the live site (hero background or gallery). With multiple attachments, the first file usually maps to hero when they say so; extra files often belong in gallery unless their text says otherwise. Never AI-generate a replacement when they supplied the asset unless they clearly ask for a new AI photo instead.
        - generate_hero_image: ONLY when the user asked for a hero/background photo and nothing else.
        - apply_and_generate_hero_image: they asked for a hero photo AND another site change (layout, services, copy, extra sections). Never use generate_hero_image alone for a numbered/multi-part request.

        apply_instruction must stay the original multi-part request when they listed more than one change. Another agent applies it; do not drop services, layout, or copy work.
        SYS;
    }

    /**
     * @return list<SubmitSiteEditTurn>
     */
    public function tools(): iterable
    {
        return [
            new SubmitSiteEditTurn($this->submission),
        ];
    }
}

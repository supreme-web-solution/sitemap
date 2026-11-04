<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesAppAnthropicFast;
use App\Ai\Support\SiteEditPlanSubmission;
use App\Ai\Tools\SubmitSiteEditPlan;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Decides how to execute one site edit (copy, CSS, sections, layout presets) before tools run.
 */
#[MaxSteps(3)]
class SiteEditPlanner implements Agent, HasTools
{
    use Promptable;
    use UsesAppAnthropicFast;

    public function __construct(protected SiteEditPlanSubmission $submission) {}

    public function instructions(): Stringable|string
    {
        return <<<'SYS'
        You are the architect for AI edits on one-page local business websites (Blade + scoped section CSS + layout presets).

        Think step by step, then submit ONE plan JSON via the tool. Fields:
        - reasoning (string): brief plan the team can log — what the user wants and how you will achieve it.
        - copy_instruction (string|null): only when headline, tagline, about, services, faqs, etc. must change. Null if not needed.
        - design_css_targets (string[]): section keys needing scoped CSS (header, hero, footer, services, …). Use for visual layout: logo position, alignment, spacing, colors, typography. Prefer CSS over layout presets for "redesign", "move logo", "center", "modernize".
        - design_instruction (string): precise brief for the CSS designer (include user wording). Empty if design_css_targets is empty.
        - sections_add (string[]): homepage section keys to add (e.g. reviews when user asks for testimonials).
        - sections_remove (string[]): homepage section keys to remove.
        - layout_variants (object): map section key → preset id ONLY when the user explicitly wants a different layout template/preset, not for minor visual tweaks.
        - document_ops (array): preferred way to apply concrete changes — list of {op: "set", path, value} using the editable document catalog in the user message. Set headline, variants.hero, section_css.footer, accent, etc. directly. Write full section_css strings yourself when you know the CSS; use design_css_targets only when you need the CSS specialist pass.

        Rules:
        - Do not invent business facts.
        - "Add reviews/testimonials" → sections_add includes reviews if missing; usually no CSS unless they also ask for styling.
        - Typos like "redesgn" still mean redesign → use design_css_targets for named sections.
        - Footer/header logo left/center/right → design_css_targets for that section, not layout_variants roulette.
        - Alignment (center, left, right) or spacing in any section → set section_css.<section> via document_ops (hero: flex column on .hero .container) OR design_css_targets if you cannot write the CSS yourself.
        - Simple copy tweaks ("change headline to …") → document_ops set on headline/tagline/cta_label, not copy_instruction.
        - layout_variants when the user explicitly wants a different section preset (overlay, split, columns, etc.); combine with design_css_targets when they also want alignment or spacing tweaks.
        SYS;
    }

    /**
     * @return list<SubmitSiteEditPlan>
     */
    public function tools(): iterable
    {
        return [
            new SubmitSiteEditPlan($this->submission),
        ];
    }
}

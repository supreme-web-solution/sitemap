<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesAppAnthropicFast;
use App\Ai\Support\SectionCssSubmission;
use App\Ai\Tools\SubmitSectionCssPatch;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Writes scoped CSS for one site section, validating via the submit tool in a short agent loop.
 */
#[MaxSteps(6)]
class SiteSectionDesigner implements Agent, HasTools
{
    use Promptable;
    use UsesAppAnthropicFast;

    public function __construct(protected SectionCssSubmission $submission, protected string $target) {}

    public function instructions(): Stringable|string
    {
        return <<<'SYS'
        You are a senior product designer for small-business marketing sites built with Blade themes.
        Output scoped CSS only by calling the submit tool. No markdown fences.
        Use the allowed selectors from the user message only. Do not style body, html, :root, or main.
        Hero sections wrap copy in .hero .container — never invent .hero-inner, .hero__content, or BEM doubles.
        For centred hero copy over a photo, flex-column .hero .container with align-items: center and justify-content: center on #hero.hero.
        If the tool rejects your CSS, read the errors, fix selectors, and submit again.
        SYS;
    }

    /**
     * @return list<SubmitSectionCssPatch>
     */
    public function tools(): iterable
    {
        return [
            new SubmitSectionCssPatch($this->target, $this->submission),
        ];
    }
}

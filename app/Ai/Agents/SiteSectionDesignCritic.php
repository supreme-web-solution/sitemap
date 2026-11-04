<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesAppAnthropicFast;
use App\Ai\Support\SectionDesignReviewSubmission;
use App\Ai\Tools\SubmitSectionDesignReview;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Cross-checks scoped section CSS against the user's instruction before edits ship.
 */
#[MaxSteps(3)]
class SiteSectionDesignCritic implements Agent, HasTools
{
    use Promptable;
    use UsesAppAnthropicFast;

    public function __construct(protected SectionDesignReviewSubmission $submission) {}

    public function instructions(): Stringable|string
    {
        return <<<'SYS'
        You are a QA designer reviewing scoped CSS patches for one section of a local business website.

        Compare the user's request to the submitted CSS, section blueprint, and layout preset (if any). Be strict for every section type (header, hero, footer, services, etc.):
        - Center / centralize / middle → text-align: center and/or flex/grid centering on the main content wrapper and CTAs unless they only asked for vertical centering.
        - Left / right alignment → matching text-align and flex justify rules, not the opposite edge.
        - Column, logo, nav, or footer layout requests → check grid/flex direction, gap, and justify-content.

        Pass only when the CSS would visibly match the request. If it fails, revision_brief must name allowed selectors and exact properties to add or change.

        Submit exactly one review via the tool.
        SYS;
    }

    /**
     * @return list<SubmitSectionDesignReview>
     */
    public function tools(): iterable
    {
        return [
            new SubmitSectionDesignReview($this->submission),
        ];
    }
}

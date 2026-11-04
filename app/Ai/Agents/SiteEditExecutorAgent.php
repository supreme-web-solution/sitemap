<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesAppAnthropicFast;
use App\Ai\Support\SiteEditExecutorState;
use App\Ai\Tools\SiteEdit\AddHomepageSection;
use App\Ai\Tools\SiteEdit\CompleteSiteEdit;
use App\Ai\Tools\SiteEdit\CreateCustomHomeSection;
use App\Ai\Tools\SiteEdit\DescribeSiteTemplate;
use App\Ai\Tools\SiteEdit\GetSectionBlueprint;
use App\Ai\Tools\SiteEdit\ListSectionLayoutVariants;
use App\Ai\Tools\SiteEdit\RemoveHomepageSection;
use App\Ai\Tools\SiteEdit\RequestSectionCssGeneration;
use App\Ai\Tools\SiteEdit\SetCopyPlan;
use App\Ai\Tools\SiteEdit\SetDocumentField;
use App\Models\Site;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Tool-based edit planner (Krikkit-style mutations on the site document).
 */
#[MaxSteps(14)]
class SiteEditExecutorAgent implements Agent, HasTools
{
    use Promptable;
    use UsesAppAnthropicFast;

    public function __construct(
        protected SiteEditExecutorState $state,
        protected Site $site,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<'SYS'
        You are the site edit executor for one-page local business websites (Blade theme + catalog design + JSON content).

        Workflow:
        1. Call describe_site_template to see the active theme and layout presets.
        2. For visual work, call get_section_blueprint (and list_section_layout_variants if changing preset).
        3. Apply changes with set_document_field (preferred), add_homepage_section, create_custom_home_section, or request_section_css_generation only when you cannot write CSS.
        4. Use set_copy_plan only for multi-field rewrites (services, about, FAQs).
        5. Always finish with complete_site_edit.

        Rules:
        - Do not change template unless the user explicitly asks.
        - Hero alignment uses .hero .container — never invent .hero-inner.
        - Single headline/tagline/button changes → set_document_field, not set_copy_plan.
        - New arbitrary homepage block → create_custom_home_section.
        SYS;
    }

    public function tools(): iterable
    {
        return [
            new DescribeSiteTemplate($this->state, $this->site),
            new GetSectionBlueprint($this->state, $this->site),
            new ListSectionLayoutVariants($this->state, $this->site),
            new SetDocumentField($this->state, $this->site),
            new AddHomepageSection($this->state, $this->site),
            new RemoveHomepageSection($this->state, $this->site),
            new CreateCustomHomeSection($this->state, $this->site),
            new RequestSectionCssGeneration($this->state, $this->site),
            new SetCopyPlan($this->state, $this->site),
            new CompleteSiteEdit($this->state, $this->site),
        ];
    }
}

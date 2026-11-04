<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesAppAnthropic;
use App\Ai\Support\SiteImagePlanSubmission;
use App\Ai\Tools\SubmitSiteImagePlan;
use App\Services\SiteBuilder\SiteImageAspectRatio;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Chooses prompt, aspect ratio, and placement for one site photo from live context.
 */
#[MaxSteps(3)]
class SiteImagePlanner implements Agent, HasTools
{
    use Promptable;
    use UsesAppAnthropic;

    public function __construct(protected SiteImagePlanSubmission $submission) {}

    public function instructions(): Stringable|string
    {
        $ratios = implode(', ', SiteImageAspectRatio::OPENROUTER);

        return <<<SYS
        You plan one AI-generated photo for a local business website.

        Submit plan_json via the tool with:
        - reasoning: why this framing fits the slot and layout (one short paragraph).
        - prompt: photorealistic image prompt (no text/logos/watermarks in the image).
        - aspect_ratio: one of {$ratios} — pick from how the photo is used on the page, not a default.
        - placement: short label such as hero_background, hero_side_column, hero_card, gallery, or about_accent.

        Rules:
        - Hero layout "overlay" or "photo-top" → wide ratios (16:9 or 21:9), subject works as background with headline overlay.
        - Hero "split" → photo beside text, often 3:4 or 4:5 vertical emphasis.
        - Hero "card" → 4:5 or 3:4, subject readable in a card over the scene.
        - Photo index 0 is almost always the hero; other indices are gallery/supporting — often 4:3 or 1:1.
        - Match business category and city; respect user hints; avoid cliché bathroom close-ups for cleaning brands.
        SYS;
    }

    /**
     * @return list<SubmitSiteImagePlan>
     */
    public function tools(): iterable
    {
        return [
            new SubmitSiteImagePlan($this->submission),
        ];
    }
}

<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesAppAnthropic;
use App\Ai\Support\SitePlanSubmission;
use App\Ai\Tools\SubmitSitePlan;
use App\Services\SiteBuilder\AiSitePlanner;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Plans or revises site content; validation happens in {@see AiSitePlanner}.
 */
#[MaxSteps(4)]
class SitePlanDesigner implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;
    use UsesAppAnthropic;

    public function __construct(
        protected SitePlanSubmission $submission,
        protected string $systemInstructions,
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->systemInstructions;
    }

    /**
     * @return list<SubmitSitePlan>
     */
    public function tools(): iterable
    {
        return [
            new SubmitSitePlan($this->submission),
        ];
    }
}

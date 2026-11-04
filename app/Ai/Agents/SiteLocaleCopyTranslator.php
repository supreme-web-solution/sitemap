<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesAppAnthropic;
use App\Ai\Support\LocaleCopySubmission;
use App\Ai\Tools\SubmitLocaleCopy;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Translates public site copy into another locale.
 */
#[MaxSteps(3)]
class SiteLocaleCopyTranslator implements Agent, HasTools
{
    use Promptable;
    use UsesAppAnthropic;

    public function __construct(protected LocaleCopySubmission $submission) {}

    public function instructions(): Stringable|string
    {
        return <<<'SYS'
        You translate website copy. Return only JSON matching the input shape via the submit tool.
        Keep facts, names, phone numbers and addresses unchanged. Do not invent offers.
        SYS;
    }

    /**
     * @return list<SubmitLocaleCopy>
     */
    public function tools(): iterable
    {
        return [
            new SubmitLocaleCopy($this->submission),
        ];
    }
}

<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesAppAnthropic;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Drafts a four-step follow-up email sequence for outreach.
 */
class FollowUpSequenceDesigner implements Agent, HasStructuredOutput
{
    use Promptable;
    use UsesAppAnthropic;

    public function __construct(protected string $systemInstructions) {}

    public function instructions(): Stringable|string
    {
        return $this->systemInstructions;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'steps' => $schema->array()
                ->min(4)
                ->max(4)
                ->required()
                ->items($schema->object([
                    'delay_days' => $schema->integer()->required(),
                    'subject' => $schema->string()->required(),
                    'body' => $schema->string()->required(),
                ])),
        ];
    }
}

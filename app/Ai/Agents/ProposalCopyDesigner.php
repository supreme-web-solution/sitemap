<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesAppAnthropic;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Drafts the introduction and terms for a client proposal.
 */
class ProposalCopyDesigner implements Agent, HasStructuredOutput
{
    use Promptable;
    use UsesAppAnthropic;

    public function __construct(protected string $systemInstructions) {}

    public function instructions(): Stringable|string
    {
        return $this->systemInstructions;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'intro' => $schema->string()->required(),
            'terms' => $schema->string()->required(),
        ];
    }
}

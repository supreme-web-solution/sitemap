<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesAppAnthropic;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Research brief for a local-business niche and geography.
 */
class NicheBriefDesigner implements Agent, HasStructuredOutput
{
    use Promptable;
    use UsesAppAnthropic;

    public function instructions(): Stringable|string
    {
        return <<<'SYS'
        You help a web agency research local business niches. Be practical and honest.
        Do not invent statistics, market sizes, or percentages. No fake urgency.
        Return structured JSON only.
        SYS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'searchQueries' => $schema->array()->min(3)->max(6)->required()->items($schema->string()),
            'idealCustomer' => $schema->string()->required(),
            'objections' => $schema->array()->min(3)->max(6)->required()->items($schema->string()),
            'openingLine' => $schema->string()->required(),
        ];
    }
}

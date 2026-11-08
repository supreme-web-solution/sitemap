<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesAppAnthropicFast;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Drafts published blog posts for a local business website.
 */
class SiteBlogPostDesigner implements Agent, HasStructuredOutput
{
    use Promptable;
    use UsesAppAnthropicFast;

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
            'posts' => $schema->array()
                ->min(1)
                ->max(5)
                ->required()
                ->items($schema->object([
                    'title' => $schema->string()->required(),
                    'excerpt' => $schema->string()->required(),
                    'body' => $schema->string()->required(),
                ])),
        ];
    }
}

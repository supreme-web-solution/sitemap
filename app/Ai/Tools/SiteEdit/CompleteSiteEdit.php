<?php

namespace App\Ai\Tools\SiteEdit;

use App\Ai\Tools\SiteEdit\Concerns\UsesSiteEditExecutorState;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CompleteSiteEdit implements Tool
{
    use UsesSiteEditExecutorState;

    public function description(): Stringable|string
    {
        return 'Finish planning after all needed tool calls. Required once per edit.';
    }

    public function handle(Request $request): Stringable|string
    {
        $this->state->reasoning = trim((string) ($request['reasoning'] ?? ''));

        $hasWork = $this->state->documentOps !== []
            || $this->state->sectionsAdd !== []
            || $this->state->sectionsRemove !== []
            || $this->state->layoutVariants !== []
            || filled($this->state->copyInstruction)
            || $this->state->designCssTargets !== [];

        $this->state->finished = true;

        if (! $hasWork) {
            return 'Warning: no changes recorded yet. Call set_document_field or other tools first, then complete again.';
        }

        return 'Edit plan complete.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reasoning' => $schema->string()->required()->description('One short paragraph for logs'),
        ];
    }
}

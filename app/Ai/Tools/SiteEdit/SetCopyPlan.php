<?php

namespace App\Ai\Tools\SiteEdit;

use App\Ai\Tools\SiteEdit\Concerns\UsesSiteEditExecutorState;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SetCopyPlan implements Tool
{
    use UsesSiteEditExecutorState;

    public function description(): Stringable|string
    {
        return 'Request a full copy rewrite (services, about, FAQs). Do not use for single headline/tagline — use set_document_field instead.';
    }

    public function handle(Request $request): Stringable|string
    {
        $instruction = trim((string) ($request['instruction'] ?? ''));

        if ($instruction === '') {
            return 'Rejected: instruction required.';
        }

        $this->state->copyInstruction = $instruction;

        return 'Accepted copy plan.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'instruction' => $schema->string()->required(),
        ];
    }
}

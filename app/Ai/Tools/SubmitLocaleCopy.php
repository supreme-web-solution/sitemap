<?php

namespace App\Ai\Tools;

use App\Ai\Support\LocaleCopySubmission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SubmitLocaleCopy implements Tool
{
    public function __construct(protected LocaleCopySubmission $submission) {}

    public function description(): Stringable|string
    {
        return 'Submit translated copy as JSON with the same keys and shape as the source bundle.';
    }

    public function handle(Request $request): Stringable|string
    {
        $raw = (string) ($request['copy_json'] ?? '');
        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || $decoded === []) {
            return 'Rejected: copy_json must be a non-empty JSON object.';
        }

        $this->submission->bundle = $decoded;

        return 'Accepted.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'copy_json' => $schema->string()->required(),
        ];
    }
}

<?php

namespace App\Ai\Tools;

use App\Ai\Support\SiteEditTurnSubmission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SubmitSiteEditTurn implements Tool
{
    public function __construct(protected SiteEditTurnSubmission $submission) {}

    public function description(): Stringable|string
    {
        return 'Submit how to handle this chat turn (mode, message, apply_instruction, image_hint, attached_image_placement). Infer intent from context — not keywords.';
    }

    public function handle(Request $request): Stringable|string
    {
        $raw = (string) ($request['turn_json'] ?? '');
        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || $decoded === []) {
            return 'Rejected: turn_json must be a non-empty JSON object.';
        }

        $this->submission->turn = $decoded;

        return 'Accepted.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'turn_json' => $schema->string()->required(),
        ];
    }
}

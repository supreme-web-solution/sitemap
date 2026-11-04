<?php

namespace App\Ai\Tools;

use App\Ai\Support\SiteEditPlanSubmission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SubmitSiteEditPlan implements Tool
{
    public function __construct(protected SiteEditPlanSubmission $submission) {}

    public function description(): Stringable|string
    {
        return 'Submit the edit plan as one JSON object: reasoning, optional copy_instruction, document_ops, design_css_targets, design_instruction, sections_add, sections_remove, layout_variants.';
    }

    public function handle(Request $request): Stringable|string
    {
        $raw = (string) ($request['plan_json'] ?? '');
        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || $decoded === []) {
            return 'Rejected: plan_json must be a non-empty JSON object.';
        }

        $this->submission->plan = $decoded;

        return 'Accepted.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan_json' => $schema->string()->required(),
        ];
    }
}

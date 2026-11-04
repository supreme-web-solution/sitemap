<?php

namespace App\Ai\Tools;

use App\Ai\Support\SiteImagePlanSubmission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SubmitSiteImagePlan implements Tool
{
    public function __construct(protected SiteImagePlanSubmission $submission) {}

    public function description(): Stringable|string
    {
        return 'Submit the image generation plan: reasoning, prompt, aspect_ratio, placement.';
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

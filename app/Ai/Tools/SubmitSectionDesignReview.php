<?php

namespace App\Ai\Tools;

use App\Ai\Support\SectionDesignReviewSubmission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Records whether scoped CSS satisfies the user's design request.
 */
class SubmitSectionDesignReview implements Tool
{
    public function __construct(protected SectionDesignReviewSubmission $submission) {}

    public function description(): Stringable|string
    {
        return 'Submit your review of whether the CSS patch fulfills the user request. If not passed, give concrete revision_brief for the designer.';
    }

    public function handle(Request $request): Stringable|string
    {
        $passed = (bool) ($request['passed'] ?? false);
        $feedback = trim((string) ($request['feedback'] ?? ''));
        $revisionBrief = trim((string) ($request['revision_brief'] ?? ''));

        if (! $passed && $revisionBrief === '') {
            return 'Rejected: when passed is false, revision_brief must explain what to fix.';
        }

        $this->submission->payload = [
            'passed' => $passed,
            'feedback' => $feedback,
            'revision_brief' => $revisionBrief,
        ];

        return 'Review recorded.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'passed' => $schema->boolean()->required()->description('True only if the CSS clearly implements what the user asked.'),
            'feedback' => $schema->string()->required()->description('Short note for logs or the site owner when something was adjusted'),
            'revision_brief' => $schema->string()->required()->description('When passed is false: precise CSS changes needed. When passed is true: empty string.'),
        ];
    }
}

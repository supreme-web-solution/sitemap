<?php

namespace App\Ai\Support;

/**
 * Holds a design critic verdict after reviewing scoped CSS against the user request.
 */
final class SectionDesignReviewSubmission
{
    /** @var array{passed: bool, feedback: string, revision_brief: string}|null */
    public ?array $payload = null;
}

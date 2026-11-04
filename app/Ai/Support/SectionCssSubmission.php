<?php

namespace App\Ai\Support;

/**
 * Holds the accepted CSS patch after the design agent calls its submit tool.
 */
final class SectionCssSubmission
{
    /** @var array{css: string, summary: string}|null */
    public ?array $payload = null;
}

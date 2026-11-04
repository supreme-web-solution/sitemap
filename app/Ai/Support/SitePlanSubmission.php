<?php

namespace App\Ai\Support;

/**
 * Holds a site plan returned by the planning agent's submit tool.
 */
final class SitePlanSubmission
{
    /** @var array<string, mixed>|null */
    public ?array $plan = null;
}

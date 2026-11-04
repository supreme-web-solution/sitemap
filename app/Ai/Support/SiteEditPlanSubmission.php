<?php

namespace App\Ai\Support;

use App\Ai\Agents\SiteEditPlanner;

/**
 * Holds the edit plan returned by {@see SiteEditPlanner}.
 */
final class SiteEditPlanSubmission
{
    /** @var array<string, mixed>|null */
    public ?array $plan = null;
}

<?php

namespace App\Ai\Support;

use App\Ai\Agents\SiteEditTurnAgent;

/**
 * Holds one chat turn decision from {@see SiteEditTurnAgent}.
 */
final class SiteEditTurnSubmission
{
    /** @var array<string, mixed>|null */
    public ?array $turn = null;
}

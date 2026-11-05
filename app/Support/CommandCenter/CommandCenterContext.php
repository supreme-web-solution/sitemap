<?php

namespace App\Support\CommandCenter;

use App\Enums\CommandCenterMode;
use App\Models\Team;
use App\Models\User;

/**
 * Team-scoped context passed into Command Center AI tools.
 */
final readonly class CommandCenterContext
{
    public function __construct(
        public Team $team,
        public User $user,
        public CommandCenterMode $mode,
    ) {}

    public function autopilot(): bool
    {
        return $this->mode === CommandCenterMode::Autopilot;
    }
}

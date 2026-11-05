<?php

namespace App\Ai\Tools\CommandCenter\Concerns;

use App\Services\CommandCenter\CommandCenterActionExecutor;
use App\Services\CommandCenter\CommandCenterApprovalGate;
use App\Support\CommandCenter\CommandCenterContext;

trait InteractsWithCommandCenterActions
{
    protected function gate(): CommandCenterApprovalGate
    {
        return app(CommandCenterApprovalGate::class);
    }

    protected function executor(): CommandCenterActionExecutor
    {
        return app(CommandCenterActionExecutor::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function runOrPropose(
        CommandCenterContext $context,
        string $action,
        array $payload,
        string $summary,
    ): string {
        return $this->gate()->runOrPropose(
            $context,
            $action,
            $payload,
            $summary,
            fn () => $this->executor()->run($action, $payload, $context->team, $context->user),
        );
    }
}

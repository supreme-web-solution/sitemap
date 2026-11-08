<?php

namespace App\Jobs;

use App\Enums\AutomationTrigger;
use App\Models\Lead;
use App\Models\User;
use App\Services\Automation\AgencyWorkflowEngine;
use App\Services\CommandCenter\CommandCenterNotifier;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunAgencyWorkflow implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public Lead $lead,
        public AutomationTrigger $trigger,
        public array $context = [],
        public ?int $userId = null,
    ) {
        $this->onQueue(Queues::DEFAULT);
    }

    public function handle(AgencyWorkflowEngine $engine): void
    {
        $lead = $this->lead->fresh();

        if ($lead === null) {
            return;
        }

        $lead->loadMissing('team');

        $actor = $this->userId !== null ? User::query()->find($this->userId) : null;

        $actions = $engine->run($lead->team, $this->trigger, $lead, $this->context, $actor);

        if ($this->trigger === AutomationTrigger::CommandCenter && $actor !== null) {
            app(CommandCenterNotifier::class)->workflowFinished($lead->team, $actor, $lead->name, $actions);
        }
    }

    public static function dispatchForLead(Lead $lead, AutomationTrigger $trigger, array $context = [], ?int $userId = null): void
    {
        static::dispatch($lead, $trigger, $context, $userId);
    }
}

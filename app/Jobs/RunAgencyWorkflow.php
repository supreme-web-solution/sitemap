<?php

namespace App\Jobs;

use App\Enums\AutomationTrigger;
use App\Models\Lead;
use App\Models\User;
use App\Services\Automation\AgencyWorkflowEngine;
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

        $engine->run($lead->team, $this->trigger, $lead, $this->context, $actor);
    }

    public static function dispatchForLead(Lead $lead, AutomationTrigger $trigger, array $context = [], ?int $userId = null): void
    {
        static::dispatch($lead, $trigger, $context, $userId);
    }
}

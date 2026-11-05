<?php

namespace App\Console\Commands;

use App\Enums\AutomationTrigger;
use App\Jobs\RunAgencyWorkflow;
use App\Models\Lead;
use App\Models\Team;
use Illuminate\Console\Command;

class RunScheduledAutomations extends Command
{
    protected $signature = 'automations:run-scheduled';

    protected $description = 'Queue scheduled agency automations (website re-audits, etc.)';

    public function handle(): int
    {
        $teams = Team::query()->whereNotNull('automation')->get();
        $queued = 0;

        foreach ($teams as $team) {
            $settings = $team->automationSettings();

            if (! $settings->enabled || $settings->reauditWebsitesEveryDays < 1) {
                continue;
            }

            $cutoff = now()->subDays($settings->reauditWebsitesEveryDays);

            Lead::query()
                ->where('team_id', $team->id)
                ->whereNotNull('website')
                ->where(function ($query) use ($cutoff) {
                    $query->whereNull('website_audited_at')
                        ->orWhere('website_audited_at', '<', $cutoff);
                })
                ->orderBy('id')
                ->limit(50)
                ->each(function (Lead $lead) use (&$queued) {
                    RunAgencyWorkflow::dispatchForLead($lead, AutomationTrigger::ScheduledAudit);
                    $queued++;
                });
        }

        $this->info("Queued {$queued} scheduled audit automation(s).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\Team;
use App\Services\Prospecting\OpportunityScorer;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RescoreTeamLeads implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 80;

    public int $tries = 1;

    public int $uniqueFor = 300;

    public function __construct(public Team $team)
    {
        $this->onQueue(Queues::DEFAULT);
    }

    public function uniqueId(): string
    {
        return (string) $this->team->id;
    }

    /**
     * Recalculate opportunity scores after the team changes its weights.
     */
    public function handle(OpportunityScorer $scorer): void
    {
        $this->team->leads()->orderBy('id')->eachById(function (Lead $lead) use ($scorer) {
            $lead->setRelation('team', $this->team);
            $result = $scorer->score($lead);
            $lead->forceFill([
                'opportunity_score' => $result['score'],
                'opportunity_breakdown' => $result['breakdown'],
            ])->saveQuietly();
        }, 100);
    }
}

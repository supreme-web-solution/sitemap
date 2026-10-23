<?php

namespace App\Jobs;

use App\Enums\SiteVibe;
use App\Models\Lead;
use App\Models\SiteConcept;
use App\Services\SiteBuilder\SiteCopywriter;
use App\Support\Queues;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateSiteConcept implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * Must stay below the queue connection's retry_after.
     */
    public int $timeout = 80;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * Seconds to wait before retrying after an exception.
     */
    public int $backoff = 15;

    public function __construct(public Lead $lead, public string $batch, public SiteVibe $vibe)
    {
        $this->onQueue(Queues::AI);
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->lead->id.'-'.$this->batch.'-'.$this->vibe->value;
    }

    /**
     * Write one of the three design options for the lead.
     */
    public function handle(SiteCopywriter $copywriter): void
    {
        $draft = $copywriter->write($this->lead, $this->vibe->template());

        SiteConcept::query()->updateOrCreate(
            [
                'lead_id' => $this->lead->id,
                'batch' => $this->batch,
                'template' => $this->vibe->template(),
            ],
            [
                'team_id' => $this->lead->team_id,
                'content' => $draft->content,
            ],
        );
    }
}

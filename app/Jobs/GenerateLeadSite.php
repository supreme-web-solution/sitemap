<?php

namespace App\Jobs;

use App\Actions\Sites\GenerateSite;
use App\Enums\SiteGenerationStatus;
use App\Models\Lead;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateLeadSite implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * Must stay below the queue connection's retry_after (90 seconds by default).
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

    /**
     * Create a new job instance.
     */
    public function __construct(public Lead $lead, public ?int $designTemplateId = null)
    {
        $this->onQueue(Queues::AI);
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->lead->id;
    }

    /**
     * Execute the job.
     */
    public function handle(GenerateSite $generateSite): void
    {
        $generateSite->handle($this->lead, designTemplateId: $this->designTemplateId);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->lead->update([
            'site_generation_status' => SiteGenerationStatus::Failed,
            'site_generation_error' => __('The website could not be generated. Please try again.'),
        ]);
    }
}

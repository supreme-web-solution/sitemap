<?php

namespace App\Jobs;

use App\Actions\Sites\GenerateSite;
use App\Enums\AutomationTrigger;
use App\Enums\SiteGenerationStatus;
use App\Models\Lead;
use App\Services\SiteBuilder\SiteBuildProgress;
use App\Support\Queues;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class GenerateLeadSite implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * AI copy often needs ~90s across retries, then saving and brand images.
     * Must stay below the AI Horizon supervisor timeout (300) and Redis
     * retry_after (360).
     */
    public int $timeout = 240;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * Seconds to wait before retrying after an exception.
     */
    public int $backoff = 15;

    /**
     * Seconds the unique lock may be held.
     *
     * Without this, killing Horizon mid-build leaves a permanent lock and every
     * later click only shows "Getting ready" while nothing is queued.
     */
    public int $uniqueFor = 300;

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
     * Drop a stuck unique lock so this lead can be built again.
     */
    public static function releaseUniqueLock(int $leadId): void
    {
        $lead = new Lead;
        $lead->id = $leadId;

        Cache::lock(UniqueLock::getKey(new self($lead)))->forceRelease();
    }

    /**
     * Execute the job.
     */
    public function handle(GenerateSite $generateSite): void
    {
        SiteBuildProgress::record($this->lead->id, 'started', ['attempt' => $this->attempts()]);

        $generateSite->handle($this->lead, designTemplateId: $this->designTemplateId);

        SiteBuildProgress::finished($this->lead->id);

        RunAgencyWorkflow::dispatchForLead($this->lead->fresh(), AutomationTrigger::SiteGenerated);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        SiteBuildProgress::failed($this->lead->id, $exception?->getMessage());

        $lead = $this->lead->fresh(['site']);

        if ($lead === null) {
            return;
        }

        // A later regenerate can fail after a site already exists. Keep the site
        // usable and do not leave a permanent "could not be generated" banner.
        if ($lead->site !== null) {
            $lead->forceFill([
                'site_generation_status' => SiteGenerationStatus::Idle,
                'site_generation_error' => null,
            ])->save();

            return;
        }

        $lead->forceFill([
            'site_generation_status' => SiteGenerationStatus::Failed,
            'site_generation_error' => __('The website could not be generated. Please try again.'),
        ])->save();
    }
}

<?php

namespace App\Jobs;

use App\Enums\SiteGenerationStatus;
use App\Models\Lead;
use App\Services\LeadFinder\WebsiteEmailLookup;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class LookupLeadEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job may run.
     *
     * Stays under the database queue retry_after of 120 seconds.
     */
    public int $timeout = 100;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(public Lead $lead)
    {
        $this->onQueue(Queues::DEFAULT);
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->lead->id;
    }

    /**
     * Seconds to ignore a second lookup for the same lead.
     */
    public function uniqueFor(): int
    {
        return 150;
    }

    /**
     * Execute the job.
     */
    public function handle(WebsiteEmailLookup $lookup): void
    {
        $lead = $this->lead->fresh() ?? $this->lead;

        if ($lead->email_lookup_status !== SiteGenerationStatus::Queued) {
            return;
        }

        if (filled($lead->email)) {
            $lead->update([
                'email_lookup_status' => SiteGenerationStatus::Idle,
                'email_lookup_empty' => false,
            ]);

            return;
        }

        try {
            $email = $lookup->findForLead($lead);
        } catch (Throwable) {
            $lead->update(['email_lookup_status' => SiteGenerationStatus::Failed]);

            return;
        }

        if ($email === null) {
            $lead->update([
                'email_lookup_empty' => true,
                'email_lookup_status' => SiteGenerationStatus::Idle,
            ]);

            return;
        }

        $lead->update([
            'email' => $email,
            'email_lookup_empty' => false,
            'email_lookup_status' => SiteGenerationStatus::Idle,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->lead->update(['email_lookup_status' => SiteGenerationStatus::Failed]);
    }
}

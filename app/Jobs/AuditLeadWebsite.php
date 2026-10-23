<?php

namespace App\Jobs;

use App\Enums\LeadActivityType;
use App\Enums\SiteGenerationStatus;
use App\Models\Lead;
use App\Services\Audit\WebsiteAuditor;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class AuditLeadWebsite implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job may run.
     */
    public int $timeout = 40;

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
     * Execute the job.
     */
    public function handle(WebsiteAuditor $auditor): void
    {
        if (blank($this->lead->website)) {
            $this->lead->update(['website_audit_status' => SiteGenerationStatus::Idle]);

            return;
        }

        $audit = $auditor->audit($this->lead->website);

        $this->lead->update([
            'website_audit' => $audit,
            'website_audited_at' => now(),
            'website_audit_status' => SiteGenerationStatus::Idle,
        ]);

        $this->lead->recordActivity(
            LeadActivityType::WebsiteAudited,
            $audit['error'] === null
                ? __('Website audited, score :score/100', ['score' => $audit['score']])
                : __('Website audit failed: :error', ['error' => $audit['error']]),
        );
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->lead->update(['website_audit_status' => SiteGenerationStatus::Failed]);
    }
}

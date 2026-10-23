<?php

namespace App\Jobs;

use App\Actions\Leads\StoreLeadsFromListings;
use App\Enums\LeadSearchStatus;
use App\Models\LeadSearch;
use App\Services\LeadFinder\LeadFinder;
use App\Support\Queues;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunLeadSearch implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Seconds between checks while the provider is still running the search.
     */
    public const POLL_DELAY = 10;

    /**
     * The number of seconds the job can run before timing out.
     *
     * Must stay below the queue connection's retry_after (90 seconds by default).
     */
    public int $timeout = 75;

    /**
     * The number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Seconds to wait before retrying after an exception.
     *
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(public LeadSearch $leadSearch)
    {
        $this->onQueue(Queues::DEFAULT);
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->leadSearch->id;
    }

    /**
     * Determine the time at which the job should stop polling.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(20);
    }

    /**
     * Execute the job.
     */
    public function handle(LeadFinder $leadFinder, StoreLeadsFromListings $storeLeads): void
    {
        $search = $this->leadSearch;

        if ($search->status->isFinished()) {
            return;
        }

        try {
            $result = $search->provider_job_id
                ? $leadFinder->poll($search->provider_job_id)
                : $leadFinder->start($search);
        } catch (RequestException $exception) {
            if ($exception->response->clientError() && $exception->response->status() !== 429) {
                $search->markAsFailed($this->clientErrorMessage($exception->response->status()));

                return;
            }

            throw $exception;
        }

        match ($result->status) {
            LeadSearchStatus::Completed => DB::transaction(function () use ($search, $result, $storeLeads) {
                $storeLeads->handle($search, $result->listings);

                $search->update([
                    'status' => LeadSearchStatus::Completed,
                    'provider_job_id' => $result->providerJobId,
                    'results_count' => count($result->listings),
                    'completed_at' => now(),
                ]);
            }),
            LeadSearchStatus::Failed => $search->markAsFailed($result->error ?? 'The search could not be completed.'),
            default => $this->waitForProvider($result->providerJobId),
        };
    }

    /**
     * Record the provider job and check back on it shortly.
     */
    protected function waitForProvider(?string $providerJobId): void
    {
        $this->leadSearch->update([
            'status' => LeadSearchStatus::Running,
            'provider_job_id' => $providerJobId ?? $this->leadSearch->provider_job_id,
        ]);

        $this->release(self::POLL_DELAY);
    }

    /**
     * Get a user-facing message for a rejected provider request.
     */
    protected function clientErrorMessage(int $status): string
    {
        return match ($status) {
            401, 403 => 'The lead finder API key is missing or invalid.',
            402 => 'Your Mindcase wallet balance is too low to run this search.',
            default => 'The lead finder rejected this search. Try a more specific location.',
        };
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->leadSearch->refresh();

        if (! $this->leadSearch->status->isFinished()) {
            $this->leadSearch->markAsFailed('The search timed out. Please try again.');
        }
    }
}

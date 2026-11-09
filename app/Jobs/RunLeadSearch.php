<?php

namespace App\Jobs;

use App\Actions\Leads\StoreLeadsFromListings;
use App\Data\LeadFinderResult;
use App\Enums\LeadSearchStatus;
use App\Models\LeadSearch;
use App\Services\Ai\AiCreditLog;
use App\Services\CommandCenter\CommandCenterNotifier;
use App\Services\LeadFinder\LeadFinder;
use App\Support\Queues;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    public function handle(LeadFinder $leadFinder, StoreLeadsFromListings $storeLeads, AiCreditLog $creditLog): void
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
                $this->failSearch(
                    $search,
                    $this->clientErrorMessage($exception->response->status()),
                    $creditLog,
                    $this->clientErrorAdminDetail($exception),
                );

                return;
            }

            throw $exception;
        }

        match ($result->status) {
            LeadSearchStatus::Completed => $this->complete($search, $result, $storeLeads),
            LeadSearchStatus::Failed => $this->failSearch(
                $search,
                $result->error ?? 'The search could not be completed. Please try again.',
                $creditLog,
                $result->providerDetail,
            ),
            default => $this->waitForProvider($result->providerJobId),
        };
    }

    /**
     * Store the listings, mark the search completed, and tell the Command Center user.
     */
    protected function complete(LeadSearch $search, LeadFinderResult $result, StoreLeadsFromListings $storeLeads): void
    {
        DB::transaction(function () use ($search, $result, $storeLeads) {
            $storeLeads->handle($search, $result->listings);

            $search->update([
                'status' => LeadSearchStatus::Completed,
                'provider_job_id' => $result->providerJobId,
                'results_count' => count($result->listings),
                'completed_at' => now(),
            ]);
        });

        app(CommandCenterNotifier::class)->leadSearchFinished($search);
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
     * Fail the search for the team and keep the provider detail in the admin credit log.
     */
    protected function failSearch(LeadSearch $search, string $userError, AiCreditLog $creditLog, ?string $providerDetail = null): void
    {
        $label = filled($search->place_url)
            ? 'Maps link import'
            : trim(($search->keyword ?? '').' · '.($search->location ?? ''), ' ·');

        $adminMessage = trim(implode(' ', array_filter([
            $label !== '' ? "Search “{$label}” failed." : 'Lead search failed.',
            $providerDetail,
        ])));

        $event = $creditLog->record(
            lead: null,
            providerLabel: 'mindcase',
            source: 'lead_search',
            message: $adminMessage,
            team: $search->team,
        );
        $creditLog->finish($event, 'Search marked failed');

        $search->markAsFailed($userError);
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
     * Keep the HTTP status and body snippet for admins.
     */
    protected function clientErrorAdminDetail(RequestException $exception): string
    {
        $status = $exception->response->status();
        $body = Str::limit(trim(preg_replace('/\s+/', ' ', $exception->response->body()) ?? ''), 400);

        return "HTTP {$status}".($body !== '' ? ": {$body}" : '');
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->leadSearch->refresh();

        if ($this->leadSearch->status->isFinished()) {
            return;
        }

        $this->failSearch(
            $this->leadSearch,
            'The search timed out. Please try again.',
            app(AiCreditLog::class),
            $exception?->getMessage() ?: 'The search job exhausted its retries.',
        );
    }
}

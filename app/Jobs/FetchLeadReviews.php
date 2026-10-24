<?php

namespace App\Jobs;

use App\Actions\Reviews\StoreLeadReviews;
use App\Enums\LeadSearchStatus;
use App\Enums\SiteGenerationStatus;
use App\Models\Lead;
use App\Services\ReviewFinder\ReviewFinder;
use App\Support\Queues;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Throwable;

class FetchLeadReviews implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Seconds between checks while the provider is still collecting reviews.
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
     *
     * When $thenGenerate is true the website is built once the reviews are in,
     * whether or not they could be fetched.
     */
    public function __construct(public Lead $lead, public bool $thenGenerate = false, public ?int $designTemplateId = null)
    {
        $this->onQueue(Queues::DEFAULT);
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->lead->id.($this->thenGenerate ? '-generate' : '');
    }

    /**
     * Determine the time at which the job should stop polling.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(10);
    }

    /**
     * Execute the job.
     */
    public function handle(ReviewFinder $finder, StoreLeadReviews $storeReviews): void
    {
        $cacheKey = $this->cacheKey();

        try {
            $providerJobId = Cache::get($cacheKey);

            $result = $providerJobId
                ? $finder->poll($providerJobId)
                : $finder->start($this->lead);
        } catch (RequestException $exception) {
            if ($exception->response->clientError() && $exception->response->status() !== 429) {
                $this->markAsFailed($this->clientErrorMessage($exception->response->status()));
                $this->finish();

                return;
            }

            throw $exception;
        }

        if ($result->status === LeadSearchStatus::Completed) {
            $storeReviews->handle($this->lead, $result->reviews);
            SummarizeLeadReviews::dispatch($this->lead);
            Cache::forget($cacheKey);
            $this->finish();

            return;
        }

        if ($result->status === LeadSearchStatus::Failed) {
            $this->markAsFailed($result->error ?? 'The reviews could not be fetched.');
            Cache::forget($cacheKey);
            $this->finish();

            return;
        }

        if ($result->providerJobId) {
            Cache::put($cacheKey, $result->providerJobId, now()->addMinutes(30));
        }

        $this->release(self::POLL_DELAY);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Cache::forget($this->cacheKey());

        $this->markAsFailed('Reviews could not be fetched in time. Please try again.');
        $this->finish();
    }

    /**
     * Build the website now that the reviews step is over, if it was requested.
     */
    protected function finish(): void
    {
        if ($this->thenGenerate) {
            GenerateLeadSite::dispatch($this->lead, $this->designTemplateId);
        }
    }

    /**
     * Record why the reviews could not be fetched.
     */
    protected function markAsFailed(string $message): void
    {
        $this->lead->update([
            'reviews_fetch_status' => SiteGenerationStatus::Failed,
            'reviews_fetch_error' => $message,
        ]);
    }

    /**
     * Get the cache key that remembers the provider job between polls.
     */
    protected function cacheKey(): string
    {
        return 'reviews-job:'.$this->lead->id;
    }

    /**
     * Get a user-facing message for a rejected provider request.
     */
    protected function clientErrorMessage(int $status): string
    {
        return match ($status) {
            401, 403 => 'The lead finder API key is missing or invalid.',
            402 => 'Your Mindcase wallet balance is too low to fetch reviews.',
            default => 'The reviews could not be fetched for this business.',
        };
    }
}

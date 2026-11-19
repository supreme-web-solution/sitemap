<?php

namespace App\Jobs;

use App\Actions\Leads\StoreLeadsFromListings;
use App\Data\BusinessListing;
use App\Data\LeadFinderResult;
use App\Enums\LeadSearchStatus;
use App\Enums\UsageMetric;
use App\Models\LeadSearch;
use App\Services\Ai\AiCreditLog;
use App\Services\Billing\PlanLimitNotifier;
use App\Services\Billing\UsageMeter;
use App\Services\CommandCenter\CommandCenterNotifier;
use App\Services\LeadFinder\LeadFinder;
use App\Services\LeadFinder\WebsiteEmailLookup;
use App\Support\Queues;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * Stays under the database queue retry_after of 120 seconds, with room for
     * one individual email lookup after the maps results are polled.
     */
    public int $timeout = 90;

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
        if ($this->leadSearch->require_email) {
            return now()->addMinutes(90);
        }

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

        $isFirstAttempt = blank($search->provider_job_id);

        if ($isFirstAttempt) {
            Log::info('[lead-search] Started', $this->logContext($search));
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
     * Keep every listing, or look up emails for the result count and save those businesses either way.
     *
     * @param  list<BusinessListing>  $listings
     * @return list<BusinessListing>
     */
    protected function listingsToStore(LeadSearch $search, array $listings): array
    {
        if (! $search->require_email) {
            return $listings;
        }

        $progress = Cache::get($this->placeEmailKey($search), []);
        $chosen = array_map(function (BusinessListing $listing) use ($progress) {
            $email = $progress[$listing->placeId] ?? null;

            if (blank($listing->email) && filled($email)) {
                return $listing->withEmail($email);
            }

            return $listing;
        }, $this->emailCandidates($search, $listings));

        return $chosen;
    }

    /**
     * Businesses already carrying an email come first, then only enough others
     * to reach the result count. Website lookup stays inside that count.
     *
     * @param  list<BusinessListing>  $listings
     * @return list<BusinessListing>
     */
    protected function emailCandidates(LeadSearch $search, array $listings): array
    {
        $withEmail = [];
        $withoutEmail = [];

        foreach ($listings as $listing) {
            if (filled($listing->email)) {
                $withEmail[] = $listing;
            } else {
                $withoutEmail[] = $listing;
            }
        }

        $limit = max(1, (int) $search->max_results);
        $chosen = array_slice($withEmail, 0, $limit);

        if (count($chosen) < $limit) {
            $chosen = [
                ...$chosen,
                ...array_slice($withoutEmail, 0, $limit - count($chosen)),
            ];
        }

        return $chosen;
    }

    /**
     * Check one selected business per attempt, the same way the email button does.
     *
     * @param  list<BusinessListing>  $listings
     */
    protected function deferIndividualEmailLookup(LeadSearch $search, LeadFinderResult $result, array $listings): bool
    {
        if (! $search->require_email || ! $this->needsIndividualEmailLookup($search, $listings)) {
            return false;
        }

        if (! Cache::has($this->emailPassKey($search))) {
            $this->pauseForEmailLookup($search, $result);
            Cache::put($this->emailPassKey($search), true, now()->addHours(2));

            return true;
        }

        $progress = Cache::get($this->placeEmailKey($search), []);
        $next = collect($this->emailCandidates($search, $listings))->first(
            fn (BusinessListing $listing) => blank($listing->email)
                && ! array_key_exists($listing->placeId, $progress)
                && (filled($listing->mapsUrl) || filled($listing->placeId)),
        );

        if (! $next instanceof BusinessListing) {
            return false;
        }

        try {
            $email = app(WebsiteEmailLookup::class)->emailFromMaps($next->mapsUrl ?: $next->placeId);
        } catch (Throwable) {
            $email = null;
        }

        $progress[$next->placeId] = $email ?? '';
        Cache::put($this->placeEmailKey($search), $progress, now()->addHours(2));
        $this->pauseForEmailLookup($search, $result);

        return true;
    }

    /**
     * @param  list<BusinessListing>  $listings
     */
    protected function needsIndividualEmailLookup(LeadSearch $search, array $listings): bool
    {
        return collect($this->emailCandidates($search, $listings))->contains(
            fn (BusinessListing $listing) => blank($listing->email)
                && (filled($listing->mapsUrl) || filled($listing->placeId)),
        );
    }

    protected function pauseForEmailLookup(LeadSearch $search, LeadFinderResult $result): void
    {
        $search->update([
            'status' => LeadSearchStatus::Running,
            'provider_job_id' => $result->providerJobId ?? $search->provider_job_id,
        ]);

        $this->release(1);
    }

    protected function emailPassKey(LeadSearch $search): string
    {
        return 'lead-search:'.$search->id.':email-pass';
    }

    protected function placeEmailKey(LeadSearch $search): string
    {
        return 'lead-search:'.$search->id.':place-emails';
    }

    /**
     * Store the listings, mark the search completed, and tell the Command Center user.
     */
    protected function complete(LeadSearch $search, LeadFinderResult $result, StoreLeadsFromListings $storeLeads): void
    {
        /** @var array{found: int, created: int, updated: int, skipped_confirmed: int, skipped_limit: int, remaining_before: int|null} $stats */
        $stats = [];

        if ($this->deferIndividualEmailLookup($search, $result, $result->listings)) {
            return;
        }

        try {
            DB::transaction(function () use ($search, $result, $storeLeads, &$stats) {
                $stats = $storeLeads->handle($search, $this->listingsToStore($search, $result->listings));

                $search->update([
                    'status' => LeadSearchStatus::Completed,
                    'provider_job_id' => $result->providerJobId,
                    'results_count' => $stats['found'],
                    'leads_created_count' => $stats['created'],
                    'leads_updated_count' => $stats['updated'],
                    'leads_skipped_limit_count' => $stats['skipped_limit'],
                    'completed_at' => now(),
                ]);
            });
        } catch (QueryException $exception) {
            Log::error('[lead-search] Store failed', [
                ...$this->logContext($search),
                'listings' => count($result->listings),
                'sql_state' => $exception->errorInfo[0] ?? null,
                'driver_code' => $exception->errorInfo[1] ?? null,
                'error' => $exception->errorInfo[2]
                    ?? Str::before($exception->getMessage(), ' (Connection:'),
            ]);

            $this->failSearch(
                $search,
                'The search finished but the results could not be saved. Please try again.',
                app(AiCreditLog::class),
                $exception->errorInfo[2] ?? 'Database error while storing leads',
            );

            return;
        }

        $search->refresh();

        Log::info('[lead-search] Completed', [
            ...$this->logContext($search),
            'provider_listings' => $stats['found'],
            'leads_created' => $stats['created'],
            'leads_updated' => $stats['updated'],
            'skipped_confirmed' => $stats['skipped_confirmed'],
            'skipped_limit' => $stats['skipped_limit'],
            'leads_remaining_before' => $stats['remaining_before'],
            'seconds' => $search->created_at?->diffInSeconds($search->completed_at ?? now()),
        ]);

        if ($stats['skipped_limit'] > 0) {
            $limit = app(UsageMeter::class)->limit($search->team, UsageMetric::Leads);

            if ($limit !== null) {
                $label = filled($search->place_url)
                    ? 'Maps link import'
                    : trim(($search->keyword ?? '').' · '.($search->location ?? ''), ' ·');

                app(PlanLimitNotifier::class)->notify(
                    $search->team,
                    UsageMetric::Leads,
                    $limit,
                    $stats['skipped_limit'],
                    $label !== ''
                        ? __('Search “:label” found businesses that could not be saved.', ['label' => $label])
                        : __('A lead search found businesses that could not be saved.'),
                );
            }
        }

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

        Log::info('[lead-search] Failed', [
            ...$this->logContext($search->fresh()),
            'error' => $userError,
            'provider_detail' => $providerDetail,
        ]);
    }

    /**
     * Shared context for lead-search log lines.
     *
     * @return array<string, mixed>
     */
    protected function logContext(LeadSearch $search): array
    {
        return [
            'lead_search_id' => $search->id,
            'team_id' => $search->team_id,
            'keyword' => $search->keyword,
            'location' => $search->location,
            'place_url' => $search->place_url,
            'max_results' => $search->max_results,
            'status' => $search->status->value,
        ];
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

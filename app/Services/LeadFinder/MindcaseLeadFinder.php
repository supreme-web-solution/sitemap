<?php

namespace App\Services\LeadFinder;

use App\Data\LeadFinderResult;
use App\Enums\LeadSearchStatus;
use App\Models\LeadSearch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class MindcaseLeadFinder implements LeadFinder
{
    /**
     * Seconds Mindcase may hold a run request open before returning a job id.
     */
    protected const WAIT_TIMEOUT = 45;

    public function __construct(
        protected ?string $apiKey,
        protected string $baseUrl,
    ) {
        //
    }

    /**
     * Start a search with the provider, returning rows if it finishes quickly.
     */
    public function start(LeadSearch $search): LeadFinderResult
    {
        $response = $this->client()
            ->timeout(self::WAIT_TIMEOUT + 10)
            ->post('data/google-maps/places/run?wait=true&wait_timeout='.self::WAIT_TIMEOUT, [
                'params' => $this->searchParams($search),
            ])
            ->throw();

        return $this->toResult($response->json());
    }

    /**
     * Check on a search that the provider is still running.
     */
    public function poll(string $providerJobId): LeadFinderResult
    {
        $response = $this->client()
            ->timeout(15)
            ->retry([500, 2000], 0, fn (Throwable $exception) => $exception instanceof ConnectionException
                || ($exception instanceof RequestException
                    && ($exception->response->serverError() || $exception->response->status() === 429)))
            ->get('jobs/'.rawurlencode($providerJobId).'/results')
            ->throw();

        $payload = $response->json();
        $payload['job_id'] ??= $providerJobId;

        return $this->toResult($payload);
    }

    /**
     * Build the agent parameters for a keyword search or a single place lookup.
     *
     * @return array<string, mixed>
     */
    protected function searchParams(LeadSearch $search): array
    {
        if ($search->place_url) {
            return ['urls' => [$search->place_url]];
        }

        return array_filter([
            'keywords' => $search->keyword,
            'location' => $search->location,
            'maxResults' => $search->max_results,
            'websiteFilter' => $search->website_filter->value,
            'minimumRating' => $this->minimumRatingOption($search->minimum_rating),
        ], fn ($value) => $value !== null);
    }

    /**
     * Build the base HTTP client for the Mindcase API.
     */
    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken((string) $this->apiKey)
            ->acceptJson()
            ->connectTimeout(5);
    }

    /**
     * Convert a Mindcase job payload into a lead finder result.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function toResult(array $payload): LeadFinderResult
    {
        $status = $payload['status'] ?? null;
        $jobId = isset($payload['job_id']) ? (string) $payload['job_id'] : null;

        if ($status === 'completed' && ! ($payload['truncated'] ?? false)) {
            return new LeadFinderResult(
                status: LeadSearchStatus::Completed,
                providerJobId: $jobId,
                listings: $this->toListings($payload['data'] ?? []),
            );
        }

        if (in_array($status, ['failed', 'cancelled', 'rejected_balance'], true)) {
            $raw = trim((string) ($payload['error'] ?? ''));
            $detail = $raw !== '' ? $raw : 'Mindcase status: '.$status;

            return new LeadFinderResult(
                status: LeadSearchStatus::Failed,
                providerJobId: $jobId,
                error: $status === 'rejected_balance'
                    ? 'Your Mindcase wallet balance is too low to run this search.'
                    : 'The search could not be completed. Please try again.',
                providerDetail: $status === 'rejected_balance'
                    ? 'Mindcase wallet balance is too low (rejected_balance).'
                    : $detail,
            );
        }

        return new LeadFinderResult(
            status: LeadSearchStatus::Running,
            providerJobId: $jobId,
        );
    }

    /**
     * Map Mindcase rows to business listings, skipping closed or incomplete places.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<BusinessListing>
     */
    protected function toListings(array $rows): array
    {
        return collect($rows)
            ->map(fn (array $row) => MapsPlaceRow::from($row)?->toListing())
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Map a numeric minimum rating to the Mindcase option name.
     */
    protected function minimumRatingOption(?float $rating): ?string
    {
        return match ($rating) {
            2.0 => 'two',
            2.5 => 'twoAndHalf',
            3.0 => 'three',
            3.5 => 'threeAndHalf',
            4.0 => 'four',
            4.5 => 'fourAndHalf',
            default => null,
        };
    }
}

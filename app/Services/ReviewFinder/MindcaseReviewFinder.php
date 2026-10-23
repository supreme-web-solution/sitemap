<?php

namespace App\Services\ReviewFinder;

use App\Data\PlaceReview;
use App\Data\ReviewFinderResult;
use App\Enums\LeadSearchStatus;
use App\Models\Lead;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class MindcaseReviewFinder implements ReviewFinder
{
    /**
     * Seconds Mindcase may hold a run request open before returning a job id.
     */
    protected const WAIT_TIMEOUT = 45;

    public function __construct(
        protected ?string $apiKey,
        protected string $baseUrl,
        protected int $maxReviews = 60,
    ) {
        //
    }

    /**
     * Start collecting a business's reviews, returning them if the provider finishes quickly.
     */
    public function start(Lead $lead): ReviewFinderResult
    {
        $response = $this->client()
            ->timeout(self::WAIT_TIMEOUT + 10)
            ->post('data/google-maps/reviews/run?wait=true&wait_timeout='.self::WAIT_TIMEOUT, [
                'params' => [
                    'urls' => [['url' => $lead->reviewsUrl()]],
                    'maxReviews' => $this->maxReviews,
                    'sortBy' => 'mostRelevant',
                    'language' => 'en',
                ],
            ])
            ->throw();

        return $this->toResult($response->json());
    }

    /**
     * Check on a collection run that the provider is still working on.
     */
    public function poll(string $providerJobId): ReviewFinderResult
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
     * Convert a Mindcase job payload into a review finder result.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function toResult(array $payload): ReviewFinderResult
    {
        $status = $payload['status'] ?? null;
        $jobId = isset($payload['job_id']) ? (string) $payload['job_id'] : null;

        if ($status === 'completed' && ! ($payload['truncated'] ?? false)) {
            return new ReviewFinderResult(
                status: LeadSearchStatus::Completed,
                providerJobId: $jobId,
                reviews: $this->toReviews($payload['data'] ?? []),
            );
        }

        if (in_array($status, ['failed', 'cancelled', 'rejected_balance'], true)) {
            return new ReviewFinderResult(
                status: LeadSearchStatus::Failed,
                providerJobId: $jobId,
                error: $status === 'rejected_balance'
                    ? 'Your Mindcase wallet balance is too low to fetch reviews.'
                    : (string) ($payload['error'] ?? 'The reviews could not be fetched.'),
            );
        }

        return new ReviewFinderResult(status: LeadSearchStatus::Running, providerJobId: $jobId);
    }

    /**
     * Map Mindcase rows to reviews, skipping rows without a reviewer or any text.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<PlaceReview>
     */
    protected function toReviews(array $rows): array
    {
        return collect($rows)
            ->map(function (array $row): ?PlaceReview {
                $author = trim((string) ($row['reviewer'] ?? ''));
                $body = $this->body($row);
                $stars = (int) round((float) ($row['stars'] ?? 0));

                if ($author === '' || $body === '' || $stars < 1 || $stars > 5) {
                    return null;
                }

                return new PlaceReview(
                    externalId: sha1((string) ($row['reviewUrl'] ?? $author.'|'.$body)),
                    authorName: $author,
                    rating: $stars,
                    body: $body,
                    postedAt: filled($row['postedAt'] ?? null) ? (string) $row['postedAt'] : null,
                );
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Pick the English text of a review, using Google's translation when needed.
     *
     * @param  array<string, mixed>  $row
     */
    protected function body(array $row): string
    {
        $original = trim((string) ($row['reviewText'] ?? ''));
        $translated = trim((string) ($row['translatedText'] ?? ''));
        $language = strtolower((string) ($row['originalLanguage'] ?? 'en'));

        if ($translated !== '' && ! str_starts_with($language, 'en')) {
            return $translated;
        }

        return $original !== '' ? $original : $translated;
    }
}

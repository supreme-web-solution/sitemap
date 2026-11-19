<?php

namespace App\Services\LeadFinder;

use App\Data\BusinessListing;
use App\Models\Lead;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Finds a public business email when Google Maps did not include one.
 *
 * A place lookup is tried first. If that is empty and the business has a
 * website, Mindcase reads the email published on that site.
 */
class WebsiteEmailLookup
{
    /**
     * How many websites one search will ask about.
     */
    protected const WEBSITE_LIMIT = 100;

    public function __construct(
        protected ?string $apiKey,
        protected string $baseUrl,
    ) {}

    /**
     * @param  list<BusinessListing>  $listings
     * @return list<BusinessListing>
     */
    public function fillListings(array $listings): array
    {
        $websites = [];

        foreach ($listings as $listing) {
            if (blank($listing->email) && filled($listing->website)) {
                $websites[] = $listing->website;
            }
        }

        if ($websites === []) {
            return $listings;
        }

        try {
            $found = $this->emailsByHost($websites);
        } catch (Throwable) {
            return $listings;
        }

        return array_map(function (BusinessListing $listing) use ($found) {
            if (filled($listing->email) || blank($listing->website)) {
                return $listing;
            }

            $email = $found[$this->host($listing->website)] ?? null;

            return $email === null ? $listing : $listing->withEmail($email);
        }, $listings);
    }

    /**
     * Look up one lead. Returns null when no public email is found.
     */
    public function findForLead(Lead $lead, ?string $website = null): ?string
    {
        $place = $lead->maps_url ?: $lead->place_id;

        if (filled($place)) {
            $email = $this->emailFromPlace($place);

            if ($email !== null) {
                return $email;
            }
        }

        $website = filled($website) ? $website : $lead->website;

        if (blank($website)) {
            return null;
        }

        return $this->emailsByHost([$website])[$this->host($website)] ?? null;
    }

    /**
     * @param  list<string>  $websites
     * @return array<string, string>
     */
    public function emailsByHost(array $websites): array
    {
        $websites = array_slice(array_values(array_unique(array_filter($websites))), 0, self::WEBSITE_LIMIT);

        if ($websites === []) {
            return [];
        }

        $payload = $this->run('data/leads/contacts/run', [
            'websites' => $websites,
            'maxResults' => count($websites),
        ]);

        $found = [];

        foreach ($payload['data'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $email = $this->firstEmail($row['email'] ?? null) ?? $this->firstEmail($row['emails'] ?? null);
            $host = $this->host($row['domain'] ?? null)
                ?? $this->host(is_string($row['website'] ?? null) ? $row['website'] : null)
                ?? $this->host(is_string($row['inputUrl'] ?? null) ? $row['inputUrl'] : null);

            if ($email !== null && $host !== null) {
                $found[$host] = $email;
            }
        }

        return $found;
    }

    protected function emailFromPlace(string $place): ?string
    {
        $payload = $this->run('data/google-maps/places/run', [
            'urls' => [$place],
        ]);

        $row = $payload['data'][0] ?? null;

        if (! is_array($row)) {
            return null;
        }

        return $this->firstEmail($row['emails'] ?? null) ?? $this->firstEmail($row['email'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function run(string $path, array $params): array
    {
        $payload = $this->client()
            ->timeout(30)
            ->post($path.'?wait=true&wait_timeout=20', ['params' => $params])
            ->throw()
            ->json();

        $payload = is_array($payload) ? $payload : [];
        $deadline = time() + 40;

        while (! in_array($payload['status'] ?? null, ['completed', 'failed', 'cancelled', 'rejected_balance'], true)
            && filled($payload['job_id'] ?? null)
            && time() < $deadline) {
            sleep(3);

            $next = $this->client()
                ->timeout(15)
                ->get('jobs/'.rawurlencode((string) $payload['job_id']).'/results')
                ->throw()
                ->json();

            $payload = is_array($next) ? $next : [];
        }

        return $payload;
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/').'/')
            ->withToken((string) $this->apiKey)
            ->acceptJson()
            ->connectTimeout(5);
    }

    protected function host(?string $website): ?string
    {
        if (blank($website)) {
            return null;
        }

        $website = str_contains($website, '://') ? $website : 'https://'.$website;
        $host = parse_url($website, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return strtolower((string) preg_replace('/^www\./', '', $host));
    }

    protected function firstEmail(mixed $value): ?string
    {
        $items = is_array($value) ? $value : [$value];

        foreach ($items as $item) {
            if (is_string($item) && filter_var($item, FILTER_VALIDATE_EMAIL)) {
                return $item;
            }
        }

        return null;
    }
}

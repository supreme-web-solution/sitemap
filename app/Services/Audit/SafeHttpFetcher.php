<?php

namespace App\Services\Audit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;

/**
 * Fetches user-supplied URLs while guarding against server-side request forgery.
 *
 * Every hop is validated: only http(s) on standard ports, no credentials in the
 * URL, and every resolved address must be public. The validated address is
 * pinned for the request so DNS cannot change between the check and the fetch.
 */
class SafeHttpFetcher
{
    private ?Client $pinnedClient = null;

    /**
     * Fetch a page and follow a limited number of safe redirects.
     *
     * @return array{url: string, status: int, body: string, bytes: int, elapsed_ms: int, truncated: bool}
     *
     * @throws UnsafeUrlException
     * @throws ConnectionException
     */
    public function fetch(string $url): array
    {
        $maxRedirects = (int) config('prospecting.audit.max_redirects');

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            [$host, $port, $ip] = $this->validate($url);

            $startedAt = hrtime(true);

            $response = $this->pinnedGet($url, $host, $port, $ip);

            $elapsedMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

            if ($response->redirect() && $response->header('Location') !== '') {
                $url = $this->absoluteUrl($url, $response->header('Location'));

                continue;
            }

            [$body, $truncated] = $this->readBody($response->toPsrResponse()->getBody());

            $page = [
                'url' => $url,
                'status' => $response->status(),
                'body' => $body,
                'bytes' => strlen($body),
                'elapsed_ms' => $elapsedMs,
                'truncated' => $truncated,
            ];

            if (AuditHttpFailure::shouldRetryViaProxy($page)) {
                return $this->proxyFetch($url);
            }

            return $page;
        }

        throw new UnsafeUrlException('Too many redirects.');
    }

    /**
     * Request a validated URL with the resolved IP pinned (requires the cURL handler).
     */
    protected function pinnedGet(string $url, string $host, int $port, string $ip): Response
    {
        if (! $this->shouldUsePinnedGuzzleClient()) {
            return Http::timeout((int) config('prospecting.audit.timeout_seconds'))
                ->withHeaders($this->browserHeaders())
                ->withOptions(['allow_redirects' => false])
                ->get($url);
        }

        try {
            $psr = $this->pinnedGuzzleClient()->request('GET', $url, [
                'curl' => [
                    CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"],
                ],
            ]);
        } catch (GuzzleConnectException|GuzzleException $exception) {
            throw new ConnectionException($exception->getMessage(), previous: $exception);
        }

        return new Response($psr);
    }

    /**
     * Fetch through ScraperAPI after bot protection blocks a direct request.
     *
     * @return array{url: string, status: int, body: string, bytes: int, elapsed_ms: int, truncated: bool}
     *
     * @throws UnsafeUrlException
     */
    protected function proxyFetch(string $url): array
    {
        $this->validate($url);

        $startedAt = hrtime(true);

        $response = Http::timeout((int) config('prospecting.audit.scraper_timeout_seconds'))
            ->get('https://api.scraperapi.com/', [
                'api_key' => (string) config('prospecting.audit.scraper_api_key'),
                'url' => $url,
                'render' => config('prospecting.audit.scraper_render') ? 'true' : 'false',
            ]);

        $elapsedMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);
        $body = $response->body();
        $limit = (int) config('prospecting.audit.max_bytes');
        $truncated = strlen($body) > $limit;

        if ($truncated) {
            $body = substr($body, 0, $limit);
        }

        return [
            'url' => $url,
            'status' => $response->status(),
            'body' => $body,
            'bytes' => strlen($body),
            'elapsed_ms' => $elapsedMs,
            'truncated' => $truncated,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function browserHeaders(): array
    {
        return [
            'User-Agent' => (string) config('prospecting.audit.user_agent'),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Cache-Control' => 'no-cache',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
        ];
    }

    /**
     * Tests use Laravel's HTTP fake; production needs cURL DNS pinning via Guzzle.
     */
    protected function shouldUsePinnedGuzzleClient(): bool
    {
        return ! app()->runningUnitTests();
    }

    protected function pinnedGuzzleClient(): Client
    {
        if ($this->pinnedClient !== null) {
            return $this->pinnedClient;
        }

        return $this->pinnedClient = new Client([
            'handler' => HandlerStack::create(new CurlHandler),
            'timeout' => (float) config('prospecting.audit.timeout_seconds'),
            'allow_redirects' => false,
            'http_errors' => false,
            'headers' => $this->browserHeaders(),
        ]);
    }

    /**
     * Ensure a URL is safe to request and return its host, port and pinned IP.
     *
     * @return array{0: string, 1: int, 2: string}
     *
     * @throws UnsafeUrlException
     */
    public function validate(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new UnsafeUrlException('The URL is not valid.');
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new UnsafeUrlException('Only http and https URLs are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeUrlException('URLs with credentials are not allowed.');
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (! in_array($port, [80, 443], true)) {
            throw new UnsafeUrlException('Only standard web ports are allowed.');
        }

        $host = trim($parts['host'], '[]');
        $addresses = $this->resolve($host);

        if ($addresses === []) {
            throw new UnsafeUrlException('The host could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw new UnsafeUrlException('The host resolves to a private or reserved address.');
            }
        }

        return [$host, $port, $addresses[0]];
    }

    /**
     * Resolve a host to its IP addresses.
     *
     * @return list<string>
     */
    protected function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        return array_values(gethostbynamel($host) ?: []);
    }

    protected function isPublicAddress(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    protected function absoluteUrl(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (str_starts_with($location, '//')) {
            return ($parts['scheme'] ?? 'https').':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $directory = rtrim(dirname($parts['path'] ?? '/'), '/');

        return $origin.$directory.'/'.$location;
    }

    /**
     * Read at most the configured number of bytes from the response.
     *
     * @return array{0: string, 1: bool}
     */
    protected function readBody(StreamInterface $stream): array
    {
        $limit = (int) config('prospecting.audit.max_bytes');
        $body = '';

        while (! $stream->eof() && strlen($body) < $limit) {
            $body .= $stream->read(min(65_536, $limit - strlen($body)));
        }

        return [$body, ! $stream->eof()];
    }
}

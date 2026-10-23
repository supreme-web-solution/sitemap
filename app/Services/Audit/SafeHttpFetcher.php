<?php

namespace App\Services\Audit;

use Illuminate\Http\Client\ConnectionException;
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

            $response = Http::timeout((int) config('prospecting.audit.timeout_seconds'))
                ->withUserAgent((string) config('prospecting.audit.user_agent'))
                ->accept('text/html,application/xhtml+xml')
                ->withOptions([
                    'allow_redirects' => false,
                    'stream' => true,
                    'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]],
                ])
                ->get($url);

            $elapsedMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

            if ($response->redirect() && $response->header('Location') !== '') {
                $url = $this->absoluteUrl($url, $response->header('Location'));

                continue;
            }

            [$body, $truncated] = $this->readBody($response->toPsrResponse()->getBody());

            return [
                'url' => $url,
                'status' => $response->status(),
                'body' => $body,
                'bytes' => strlen($body),
                'elapsed_ms' => $elapsedMs,
                'truncated' => $truncated,
            ];
        }

        throw new UnsafeUrlException('Too many redirects.');
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

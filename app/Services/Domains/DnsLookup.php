<?php

namespace App\Services\Domains;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads public DNS records via Cloudflare DNS-over-HTTPS first, then falls back
 * to the server resolver. DoH avoids stale OS/resolver caches on Forge that can
 * lag behind public DNS after a customer updates their CNAME.
 */
class DnsLookup
{
    /**
     * Get the TXT records at the host.
     *
     * @return list<string>
     */
    public function txt(string $host): array
    {
        $fromDoh = $this->viaDoh($host, 'TXT');

        if ($fromDoh !== null) {
            return array_values(array_map(
                static fn (string $value): string => trim($value, '"'),
                $fromDoh,
            ));
        }

        return array_values(array_map(
            fn (array $record) => trim($record['txt'] ?? implode('', $record['entries'] ?? [])),
            $this->records($host, DNS_TXT),
        ));
    }

    /**
     * Get the IPv4 addresses the host resolves to.
     *
     * @return list<string>
     */
    public function a(string $host): array
    {
        $fromDoh = $this->viaDoh($host, 'A');

        if ($fromDoh !== null) {
            return $fromDoh;
        }

        return array_values(array_filter(array_map(
            fn (array $record) => $record['ip'] ?? null,
            $this->records($host, DNS_A),
        )));
    }

    /**
     * Get the names the host is an alias of.
     *
     * @return list<string>
     */
    public function cname(string $host): array
    {
        $fromDoh = $this->viaDoh($host, 'CNAME');

        if ($fromDoh !== null) {
            return array_values(array_map(
                static fn (string $target): string => strtolower(rtrim($target, '.')),
                $fromDoh,
            ));
        }

        return array_values(array_filter(array_map(
            fn (array $record) => isset($record['target']) ? strtolower(rtrim($record['target'], '.')) : null,
            $this->records($host, DNS_CNAME),
        )));
    }

    /**
     * Query Cloudflare DNS-over-HTTPS. Returns null when DoH is unreachable so
     * callers can fall back to the local resolver.
     *
     * @return list<string>|null
     */
    protected function viaDoh(string $name, string $type): ?array
    {
        try {
            $response = Http::connectTimeout(2)
                ->timeout(4)
                ->accept('application/dns-json')
                ->get('https://cloudflare-dns.com/dns-query', [
                    'name' => $name,
                    'type' => $type,
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $answers = $response->json('Answer');

        if (! is_array($answers)) {
            return [];
        }

        $values = [];

        foreach ($answers as $answer) {
            if (! is_array($answer) || ! isset($answer['data']) || ! is_string($answer['data'])) {
                continue;
            }

            $values[] = $answer['data'];
        }

        return array_values($values);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function records(string $host, int $type): array
    {
        $records = @dns_get_record($host, $type);

        return is_array($records) ? array_values($records) : [];
    }
}

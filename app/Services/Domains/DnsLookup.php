<?php

namespace App\Services\Domains;

/**
 * Reads public DNS records. Kept separate so tests can fake the network.
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
        return array_values(array_filter(array_map(
            fn (array $record) => isset($record['target']) ? strtolower(rtrim($record['target'], '.')) : null,
            $this->records($host, DNS_CNAME),
        )));
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

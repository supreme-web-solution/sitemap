<?php

namespace App\Services\Domains;

use App\Models\SiteDomain;
use Illuminate\Database\UniqueConstraintViolationException;

class DomainVerifier
{
    /**
     * Second-level suffixes where the registrable name has three labels (example.co.uk).
     *
     * @var list<string>
     */
    protected const COMPOUND_SUFFIXES = [
        'co.uk', 'org.uk', 'me.uk', 'com.au', 'net.au', 'org.au', 'co.nz', 'co.za', 'com.br',
        'com.mx', 'co.in', 'co.jp', 'com.sg', 'com.ng', 'co.ke', 'com.pk', 'com.ph',
    ];

    public function __construct(protected DnsLookup $dns) {}

    /**
     * Check the domain's DNS and mark it verified when ownership is proven.
     *
     * Ownership is proven by the TXT record, or by the domain already pointing at this app,
     * because only the owner can change either.
     */
    public function verify(SiteDomain $domain): bool
    {
        $domain->last_checked_at = now();

        if ($this->hasOwnershipRecord($domain) || $this->pointsHere($domain->hostname)) {
            return $this->markVerified($domain);
        }

        $domain->check_error = 'We could not find the DNS records yet. Changes can take a few minutes to a few hours to spread.';
        $domain->save();

        return false;
    }

    /**
     * Get the DNS records the customer needs to create.
     *
     * @return list<array{type: string, name: string, value: string, purpose: string}>
     */
    public function instructions(SiteDomain $domain): array
    {
        $records = [[
            'type' => 'TXT',
            'name' => $domain->verificationRecordName(),
            'value' => $domain->verification_token,
            'purpose' => 'Proves you own the domain',
        ]];

        $target = config('sites.cname_target');
        $ip = config('sites.server_ip');
        $apex = $this->isApex($domain->hostname);

        if ($apex && $ip) {
            $records[] = ['type' => 'A', 'name' => $domain->hostname, 'value' => $ip, 'purpose' => 'Sends visitors to your website'];
        } elseif ($apex && $target) {
            $records[] = ['type' => 'ALIAS', 'name' => $domain->hostname, 'value' => $target, 'purpose' => 'Sends visitors to your website (also called ANAME or CNAME flattening)'];
        } elseif ($target) {
            $records[] = ['type' => 'CNAME', 'name' => $domain->hostname, 'value' => $target, 'purpose' => 'Sends visitors to your website'];
        } elseif ($ip) {
            $records[] = ['type' => 'A', 'name' => $domain->hostname, 'value' => $ip, 'purpose' => 'Sends visitors to your website'];
        }

        return $records;
    }

    /**
     * Whether the hostname is a root domain (example.com) rather than a subdomain (www.example.com).
     */
    public function isApex(string $hostname): bool
    {
        $labels = substr_count($hostname, '.') + 1;

        foreach (self::COMPOUND_SUFFIXES as $suffix) {
            if (str_ends_with($hostname, '.'.$suffix)) {
                return $labels === 3;
            }
        }

        return $labels === 2;
    }

    protected function hasOwnershipRecord(SiteDomain $domain): bool
    {
        return in_array($domain->verification_token, $this->dns->txt($domain->verificationRecordName()), true);
    }

    protected function pointsHere(string $hostname): bool
    {
        $target = strtolower((string) config('sites.cname_target'));
        $ip = config('sites.server_ip');

        if ($target !== '' && in_array($target, $this->dns->cname($hostname), true)) {
            return true;
        }

        return filled($ip) && in_array($ip, $this->dns->a($hostname), true);
    }

    protected function markVerified(SiteDomain $domain): bool
    {
        $domain->verified_at ??= now();
        $domain->verified_hostname = $domain->hostname;
        $domain->check_error = null;

        try {
            $domain->save();
        } catch (UniqueConstraintViolationException) {
            $domain->verified_at = null;
            $domain->verified_hostname = null;
            $domain->check_error = 'This domain is already connected to another website.';
            $domain->save();

            return false;
        }

        return true;
    }
}

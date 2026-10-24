<?php

namespace App\Services\Preview;

use App\Http\Requests\Sites\StoreSiteDomainRequest;
use App\Models\SiteDomain;
use App\Models\Team;
use App\Services\Domains\DnsLookup;
use App\Services\Domains\DomainVerifier;
use Illuminate\Database\UniqueConstraintViolationException;

class TeamPreviewHostnameVerifier
{
    public function __construct(
        protected DnsLookup $dns,
        protected DomainVerifier $domainVerifier,
    ) {}

    /**
     * Check DNS and mark the team's preview hostname verified when ownership is proven.
     */
    public function verify(Team $team): bool
    {
        $hostname = (string) $team->preview_hostname;

        if ($hostname === '' || $team->preview_hostname_token === null) {
            return false;
        }

        $team->preview_hostname_checked_at = now();

        if ($this->hasOwnershipRecord($team) || $this->pointsHere($hostname)) {
            return $this->markVerified($team);
        }

        $team->preview_hostname_check_error = 'We could not find the DNS records yet. Changes can take a few minutes to a few hours to spread.';
        $team->save();

        return false;
    }

    /**
     * DNS records the agency should create for the preview hostname.
     *
     * @return list<array{type: string, name: string, value: string, purpose: string}>
     */
    public function instructions(Team $team): array
    {
        $hostname = (string) $team->preview_hostname;
        $token = (string) $team->preview_hostname_token;

        $records = [[
            'type' => 'TXT',
            'name' => $this->verificationRecordName($hostname),
            'value' => $token,
            'purpose' => 'Proves you own the domain',
        ]];

        $target = config('sites.cname_target');
        $ip = config('sites.server_ip');
        $apex = $this->domainVerifier->isApex($hostname);

        if ($apex && $ip) {
            $records[] = ['type' => 'A', 'name' => $hostname, 'value' => $ip, 'purpose' => 'Sends preview visitors to this app'];
        } elseif ($apex && $target) {
            $records[] = ['type' => 'ALIAS', 'name' => $hostname, 'value' => $target, 'purpose' => 'Sends preview visitors to this app (also called ANAME or CNAME flattening)'];
        } elseif ($target) {
            $records[] = ['type' => 'CNAME', 'name' => $hostname, 'value' => $target, 'purpose' => 'Sends preview visitors to this app'];
        } elseif ($ip) {
            $records[] = ['type' => 'A', 'name' => $hostname, 'value' => $ip, 'purpose' => 'Sends preview visitors to this app'];
        }

        return $records;
    }

    /**
     * Normalise and persist a new preview hostname, resetting verification.
     */
    public function assign(Team $team, string $hostname): bool
    {
        $hostname = StoreSiteDomainRequest::normalize($hostname);

        if (SiteDomain::query()->verified()->where('hostname', $hostname)->exists()) {
            return false;
        }

        $team->forceFill([
            'preview_hostname' => $hostname,
            'preview_hostname_token' => $team->preview_hostname_token ?? bin2hex(random_bytes(16)),
            'preview_hostname_verified_at' => null,
            'preview_hostname_check_error' => null,
        ])->save();

        return true;
    }

    public function verificationRecordName(string $hostname): string
    {
        return SiteDomain::VERIFICATION_PREFIX.$hostname;
    }

    protected function hasOwnershipRecord(Team $team): bool
    {
        $hostname = (string) $team->preview_hostname;

        return in_array(
            (string) $team->preview_hostname_token,
            $this->dns->txt($this->verificationRecordName($hostname)),
            true,
        );
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

    protected function markVerified(Team $team): bool
    {
        $team->preview_hostname_verified_at ??= now();
        $team->preview_hostname_check_error = null;

        try {
            $team->save();
        } catch (UniqueConstraintViolationException) {
            $team->preview_hostname_verified_at = null;
            $team->preview_hostname_check_error = 'This preview domain is already used by another agency.';
            $team->save();

            return false;
        }

        return true;
    }
}

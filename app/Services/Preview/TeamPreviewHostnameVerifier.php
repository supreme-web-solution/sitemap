<?php

namespace App\Services\Preview;

use App\Http\Requests\Sites\StoreSiteDomainRequest;
use App\Models\SiteDomain;
use App\Models\Team;
use App\Models\User;
use App\Services\Cloudflare\SyncCloudflareHostname;
use App\Services\Domains\DnsLookup;
use App\Services\Domains\DomainVerifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class TeamPreviewHostnameVerifier
{
    public function __construct(
        protected DnsLookup $dns,
        protected DomainVerifier $domainVerifier,
        protected SyncCloudflareHostname $cloudflare,
    ) {}

    /**
     * Check DNS and mark the team's preview hostname verified when ownership is proven.
     *
     * Cloudflare reporting the custom hostname + certificate as active is enough:
     * Cloudflare already validated ownership and the CNAME before issuing HTTPS.
     */
    public function verify(Team $team): bool
    {
        $hostname = (string) $team->preview_hostname;

        if ($hostname === '' || $team->preview_hostname_token === null) {
            return false;
        }

        $team->forceFill([
            'preview_hostname_checked_at' => now(),
        ])->save();

        $this->cloudflare->syncTeamPreview($team->fresh());
        $team->refresh();

        if (
            $team->previewSslIsActive()
            || $this->hasOwnershipRecord($team)
            || $this->pointsHere($hostname)
        ) {
            return $this->markVerified($team);
        }

        $team->preview_hostname_check_error = 'We could not find the DNS records yet. Changes can take a few minutes to a few hours to spread.';
        $team->save();

        return false;
    }

    /**
     * DNS records the agency should create for the preview hostname.
     *
     * @return list<array{type: string, name: string, value: string, purpose: string, status: 'done'|'needed'}>
     */
    public function instructions(Team $team): array
    {
        $hostname = (string) $team->preview_hostname;
        $token = (string) $team->preview_hostname_token;
        $ownershipDone = $team->hasVerifiedPreviewHostname() || $this->hasOwnershipRecord($team);
        $pointsHere = $this->pointsHere($hostname);
        $sslDone = $team->previewSslIsActive();

        $records = [[
            'type' => 'TXT',
            'name' => $this->verificationRecordName($hostname),
            'value' => $token,
            'purpose' => 'Proves you own the domain (MapMatic)',
            'status' => ($ownershipDone || $sslDone) ? 'done' : 'needed',
        ]];

        $target = config('sites.cname_target');
        $ip = config('sites.server_ip');
        $apex = $this->domainVerifier->isApex($hostname);

        if ($apex && $ip) {
            $records[] = [
                'type' => 'A',
                'name' => $hostname,
                'value' => $ip,
                'purpose' => 'Sends preview visitors to this app',
                'status' => ($pointsHere || $sslDone) ? 'done' : 'needed',
            ];
        } elseif ($apex && $target) {
            $records[] = [
                'type' => 'ALIAS',
                'name' => $hostname,
                'value' => $target,
                'purpose' => 'Required for HTTPS — points the hostname at MapMatic (also called ANAME or CNAME flattening)',
                'status' => ($pointsHere || $sslDone) ? 'done' : 'needed',
            ];
        } elseif ($target) {
            $records[] = [
                'type' => 'CNAME',
                'name' => $hostname,
                'value' => $target,
                'purpose' => 'Required for HTTPS — must point at '.$target.' (not the app hostname)',
                'status' => ($pointsHere || $sslDone) ? 'done' : 'needed',
            ];
        } elseif ($ip) {
            $records[] = [
                'type' => 'A',
                'name' => $hostname,
                'value' => $ip,
                'purpose' => 'Sends preview visitors to this app',
                'status' => ($pointsHere || $sslDone) ? 'done' : 'needed',
            ];
        }

        if (filled($team->preview_cloudflare_ownership_txt_name) && filled($team->preview_cloudflare_ownership_txt_value)) {
            $cfTxtDone = $sslDone || in_array(
                (string) $team->preview_cloudflare_ownership_txt_value,
                $this->dns->txt((string) $team->preview_cloudflare_ownership_txt_name),
                true,
            );

            $records[] = [
                'type' => 'TXT',
                'name' => $team->preview_cloudflare_ownership_txt_name,
                'value' => $team->preview_cloudflare_ownership_txt_value,
                'purpose' => 'Cloudflare ownership check (hostnames outside mapmaticapp.com)',
                'status' => $cfTxtDone ? 'done' : 'needed',
            ];
        }

        return $records;
    }

    /**
     * Normalise and persist a new preview hostname, resetting verification.
     *
     * @throws ValidationException
     */
    public function assign(Team $team, string $hostname): void
    {
        $hostname = StoreSiteDomainRequest::normalize($hostname);

        if (SiteDomain::query()->verified()->where('hostname', $hostname)->exists()) {
            throw ValidationException::withMessages([
                'hostname' => __('That domain is already connected to a published website.'),
            ]);
        }

        $takenBy = Team::query()
            ->where('preview_hostname', $hostname)
            ->whereKeyNot($team->id)
            ->first();

        if ($takenBy !== null) {
            throw ValidationException::withMessages([
                'hostname' => $this->previewHostnameTakenMessage($takenBy),
            ]);
        }

        if ($team->preview_hostname === $hostname) {
            if (blank($team->preview_hostname_token)) {
                $team->forceFill([
                    'preview_hostname_token' => bin2hex(random_bytes(16)),
                ])->save();
            }

            $this->cloudflare->syncTeamPreview($team->fresh());

            return;
        }

        if (filled($team->preview_cloudflare_hostname_id) || filled($team->preview_hostname)) {
            $this->cloudflare->forgetTeamPreview($team);
            $team->refresh();
        }

        try {
            $team->forceFill([
                'preview_hostname' => $hostname,
                'preview_hostname_token' => bin2hex(random_bytes(16)),
                'preview_hostname_verified_at' => null,
                'preview_hostname_check_error' => null,
                'preview_cloudflare_hostname_id' => null,
                'preview_cloudflare_status' => null,
                'preview_cloudflare_ssl_status' => null,
                'preview_cloudflare_ownership_txt_name' => null,
                'preview_cloudflare_ownership_txt_value' => null,
                'preview_cloudflare_synced_at' => null,
            ])->save();
        } catch (UniqueConstraintViolationException) {
            $takenBy = Team::query()
                ->where('preview_hostname', $hostname)
                ->whereKeyNot($team->id)
                ->first();

            throw ValidationException::withMessages([
                'hostname' => $takenBy !== null
                    ? $this->previewHostnameTakenMessage($takenBy)
                    : __('That domain is already used as another agency’s preview domain.'),
            ]);
        }

        $this->cloudflare->syncTeamPreview($team->fresh());
    }

    /**
     * Explain which team/account already owns a preview hostname.
     */
    protected function previewHostnameTakenMessage(Team $team): string
    {
        $owner = $team->owner();
        $account = $owner instanceof User
            ? trim($owner->name.' <'.$owner->email.'>')
            : null;

        if ($account !== null && $account !== '') {
            return __('That domain is already used as the preview domain for :team (:account).', [
                'team' => $team->name,
                'account' => $account,
            ]);
        }

        return __('That domain is already used as the preview domain for :team.', [
            'team' => $team->name,
        ]);
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

        $this->cloudflare->syncTeamPreview($team->fresh());

        return true;
    }
}

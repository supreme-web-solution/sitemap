<?php

namespace App\Services\Cloudflare;

use App\Models\SiteDomain;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Keep Cloudflare for SaaS custom hostnames in sync with MapMatic domains.
 *
 * Hostnames are registered as soon as a domain is added so the UI can show the
 * CNAME target and Cloudflare ownership TXT before the customer leaves DNS settings.
 */
class SyncCloudflareHostname
{
    public function __construct(protected CloudflareCustomHostnames $cloudflare) {}

    /**
     * Create or refresh the Cloudflare hostname for a site domain.
     */
    public function syncSiteDomain(SiteDomain $domain): void
    {
        if (! $this->cloudflare->enabled()) {
            return;
        }

        try {
            $state = $domain->cloudflare_hostname_id
                ? $this->cloudflare->get($domain->cloudflare_hostname_id)
                : $this->cloudflare->create($domain->hostname);

            if ($state['id'] === '') {
                return;
            }

            $payload = [
                'cloudflare_hostname_id' => $state['id'],
                'cloudflare_status' => $state['status'],
                'cloudflare_ssl_status' => $state['ssl_status'],
                'cloudflare_ownership_txt_name' => $state['ownership_txt_name'],
                'cloudflare_ownership_txt_value' => $state['ownership_txt_value'],
                'cloudflare_synced_at' => now(),
            ];

            if ($domain->isVerified()) {
                $payload['check_error'] = $this->pendingMessage($state);
            }

            $domain->forceFill($payload)->save();
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Cloudflare custom hostname sync failed.', [
                'site_domain_id' => $domain->id,
                'hostname' => $domain->hostname,
                'message' => $exception->getMessage(),
            ]);

            $domain->forceFill([
                'check_error' => $this->failureMessage($exception, $domain->isVerified()),
            ])->save();
        }
    }

    /**
     * Create or refresh the Cloudflare hostname for a preview domain.
     */
    public function syncTeamPreview(Team $team): void
    {
        if (! $this->cloudflare->enabled() || blank($team->preview_hostname)) {
            return;
        }

        $hostname = (string) $team->preview_hostname;

        try {
            $state = $team->preview_cloudflare_hostname_id
                ? $this->cloudflare->get($team->preview_cloudflare_hostname_id)
                : $this->cloudflare->create($hostname);

            if ($state['id'] === '') {
                return;
            }

            $payload = [
                'preview_cloudflare_hostname_id' => $state['id'],
                'preview_cloudflare_status' => $state['status'],
                'preview_cloudflare_ssl_status' => $state['ssl_status'],
                'preview_cloudflare_ownership_txt_name' => $state['ownership_txt_name'],
                'preview_cloudflare_ownership_txt_value' => $state['ownership_txt_value'],
                'preview_cloudflare_synced_at' => now(),
            ];

            if ($team->hasVerifiedPreviewHostname()) {
                $payload['preview_hostname_check_error'] = $this->pendingMessage($state);
            }

            $team->forceFill($payload)->save();
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Cloudflare preview hostname sync failed.', [
                'team_id' => $team->id,
                'hostname' => $hostname,
                'message' => $exception->getMessage(),
            ]);

            $team->forceFill([
                'preview_hostname_check_error' => $this->failureMessage($exception, $team->hasVerifiedPreviewHostname()),
            ])->save();
        }
    }

    /**
     * Remove the Cloudflare hostname when a site domain is disconnected.
     */
    public function forgetSiteDomain(SiteDomain $domain): void
    {
        $id = $domain->cloudflare_hostname_id;

        if (! $this->cloudflare->enabled() || blank($id)) {
            return;
        }

        try {
            $this->cloudflare->delete($id);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Remove the Cloudflare hostname when a preview domain is removed.
     */
    public function forgetTeamPreview(Team $team): void
    {
        $id = $team->preview_cloudflare_hostname_id;

        if (! $this->cloudflare->enabled() || blank($id)) {
            return;
        }

        try {
            $this->cloudflare->delete($id);
        } catch (Throwable $exception) {
            report($exception);
        }

        $team->forceFill([
            'preview_cloudflare_hostname_id' => null,
            'preview_cloudflare_status' => null,
            'preview_cloudflare_ssl_status' => null,
            'preview_cloudflare_ownership_txt_name' => null,
            'preview_cloudflare_ownership_txt_value' => null,
            'preview_cloudflare_synced_at' => null,
        ])->save();
    }

    /**
     * @param  array{status: string|null, ssl_status: string|null, verification_errors?: list<string>}  $state
     */
    protected function pendingMessage(array $state): ?string
    {
        if ($state['status'] === 'active' && $state['ssl_status'] === 'active') {
            return null;
        }

        $target = (string) config('sites.cname_target');
        $errors = $state['verification_errors'] ?? [];

        foreach ($errors as $error) {
            if (str_contains(strtolower($error), 'cname')) {
                return $target !== ''
                    ? 'Cloudflare cannot see the CNAME yet. At the domain DNS host, point this hostname to '.$target.', wait a few minutes, then click Refresh HTTPS.'
                    : 'Cloudflare cannot see the CNAME yet. Fix the CNAME at the domain DNS host, wait a few minutes, then click Refresh HTTPS.';
            }
        }

        return $target !== ''
            ? 'Ownership looks good. HTTPS is still activating — keep the CNAME on '.$target.' and click Refresh HTTPS in a minute if this does not clear.'
            : 'Ownership looks good. HTTPS is still activating — click Refresh HTTPS in a minute if this does not clear.';
    }

    protected function failureMessage(Throwable $exception, bool $verified): string
    {
        $detail = trim($exception->getMessage());
        $prefix = $verified
            ? 'Domain verified, but HTTPS could not be set up'
            : 'Could not start HTTPS setup with Cloudflare';

        if ($detail === '') {
            return $prefix.'. Try Refresh HTTPS again in a minute.';
        }

        return $prefix.': '.Str::limit($detail, 180);
    }
}

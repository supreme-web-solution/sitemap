<?php

namespace App\Services\Cloudflare;

use App\Models\SiteDomain;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keep Cloudflare for SaaS custom hostnames in sync with verified MapMatic domains.
 */
class SyncCloudflareHostname
{
    public function __construct(protected CloudflareCustomHostnames $cloudflare) {}

    /**
     * Create or refresh the Cloudflare hostname for a verified site domain.
     */
    public function syncSiteDomain(SiteDomain $domain): void
    {
        if (! $this->cloudflare->enabled() || ! $domain->isVerified()) {
            return;
        }

        try {
            $state = $domain->cloudflare_hostname_id
                ? $this->cloudflare->get($domain->cloudflare_hostname_id)
                : $this->cloudflare->create($domain->hostname);

            if ($state['id'] === '') {
                return;
            }

            $domain->forceFill([
                'cloudflare_hostname_id' => $state['id'],
                'cloudflare_status' => $state['status'],
                'cloudflare_ssl_status' => $state['ssl_status'],
                'cloudflare_synced_at' => now(),
                'check_error' => $this->pendingMessage($state),
            ])->save();
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Cloudflare custom hostname sync failed.', [
                'site_domain_id' => $domain->id,
                'hostname' => $domain->hostname,
                'message' => $exception->getMessage(),
            ]);

            $domain->forceFill([
                'check_error' => 'Domain verified, but HTTPS is still being set up. Try Check DNS again in a minute.',
            ])->save();
        }
    }

    /**
     * Create or refresh the Cloudflare hostname for a verified preview domain.
     */
    public function syncTeamPreview(Team $team): void
    {
        if (! $this->cloudflare->enabled() || ! $team->hasVerifiedPreviewHostname()) {
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

            $team->forceFill([
                'preview_cloudflare_hostname_id' => $state['id'],
                'preview_cloudflare_status' => $state['status'],
                'preview_cloudflare_ssl_status' => $state['ssl_status'],
                'preview_cloudflare_synced_at' => now(),
                'preview_hostname_check_error' => $this->pendingMessage($state),
            ])->save();
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Cloudflare preview hostname sync failed.', [
                'team_id' => $team->id,
                'hostname' => $hostname,
                'message' => $exception->getMessage(),
            ]);

            $team->forceFill([
                'preview_hostname_check_error' => 'Domain verified, but HTTPS is still being set up. Try Re-check DNS again in a minute.',
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
            'preview_cloudflare_synced_at' => null,
        ])->save();
    }

    /**
     * @param  array{status: string|null, ssl_status: string|null}  $state
     */
    protected function pendingMessage(array $state): ?string
    {
        if ($state['status'] === 'active' && $state['ssl_status'] === 'active') {
            return null;
        }

        return 'DNS is verified. HTTPS is still activating — this page updates automatically; you can also click Re-check DNS.';
    }
}

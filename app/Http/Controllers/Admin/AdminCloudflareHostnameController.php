<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteDomain;
use App\Models\Team;
use App\Services\Cloudflare\CloudflareCustomHostnames;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AdminCloudflareHostnameController extends Controller
{
    /**
     * List Cloudflare for SaaS custom hostnames and how they map to MapMatic records.
     */
    public function index(CloudflareCustomHostnames $cloudflare): Response
    {
        $enabled = $cloudflare->enabled();
        $hostnames = [];
        $error = null;

        if ($enabled) {
            try {
                $hostnames = $cloudflare->list();
            } catch (Throwable $exception) {
                report($exception);
                $error = $exception->getMessage();
            }
        }

        $domainByCfId = SiteDomain::query()
            ->whereNotNull('cloudflare_hostname_id')
            ->get(['id', 'hostname', 'site_id', 'cloudflare_hostname_id', 'verified_at', 'cloudflare_status', 'cloudflare_ssl_status'])
            ->keyBy('cloudflare_hostname_id');

        $teamByCfId = Team::query()
            ->whereNotNull('preview_cloudflare_hostname_id')
            ->get(['id', 'name', 'slug', 'preview_hostname', 'preview_cloudflare_hostname_id', 'preview_hostname_verified_at', 'preview_cloudflare_status', 'preview_cloudflare_ssl_status'])
            ->keyBy('preview_cloudflare_hostname_id');

        return Inertia::render('admin/CloudflareHostnames', [
            'enabled' => $enabled,
            'error' => $error,
            'hostnames' => collect($hostnames)->map(function (array $row) use ($domainByCfId, $teamByCfId) {
                $domain = $domainByCfId->get($row['id']);
                $team = $teamByCfId->get($row['id']);

                return [
                    'id' => $row['id'],
                    'hostname' => $row['hostname'],
                    'status' => $row['status'],
                    'sslStatus' => $row['ssl_status'],
                    'verificationErrors' => $row['verification_errors'],
                    'ownershipTxtName' => $row['ownership_txt_name'],
                    'ownershipTxtValue' => $row['ownership_txt_value'],
                    'linked' => $domain !== null
                        ? [
                            'type' => 'site_domain',
                            'label' => 'Site domain #'.$domain->id,
                            'hostname' => $domain->hostname,
                        ]
                        : ($team !== null
                            ? [
                                'type' => 'preview',
                                'label' => 'Preview · '.$team->name,
                                'hostname' => $team->preview_hostname,
                            ]
                            : null),
                ];
            })->values()->all(),
        ]);
    }

    /**
     * Delete a custom hostname from Cloudflare and clear matching MapMatic links.
     */
    public function destroy(string $hostnameId, CloudflareCustomHostnames $cloudflare): RedirectResponse
    {
        if (! $cloudflare->enabled()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cloudflare is not configured.')]);

            return back();
        }

        try {
            $cloudflare->delete($hostnameId);
        } catch (Throwable $exception) {
            report($exception);
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Could not delete hostname: :message', ['message' => $exception->getMessage()])]);

            return back();
        }

        SiteDomain::query()
            ->where('cloudflare_hostname_id', $hostnameId)
            ->update([
                'cloudflare_hostname_id' => null,
                'cloudflare_status' => null,
                'cloudflare_ssl_status' => null,
                'cloudflare_ownership_txt_name' => null,
                'cloudflare_ownership_txt_value' => null,
                'cloudflare_synced_at' => null,
            ]);

        Team::query()
            ->where('preview_cloudflare_hostname_id', $hostnameId)
            ->update([
                'preview_cloudflare_hostname_id' => null,
                'preview_cloudflare_status' => null,
                'preview_cloudflare_ssl_status' => null,
                'preview_cloudflare_ownership_txt_name' => null,
                'preview_cloudflare_ownership_txt_value' => null,
                'preview_cloudflare_synced_at' => null,
            ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom hostname deleted from Cloudflare.')]);

        return back();
    }
}

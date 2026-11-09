<?php

namespace App\Http\Controllers\Sites;

use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sites\StoreSiteDomainRequest;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use App\Services\Cloudflare\SyncCloudflareHostname;
use App\Services\Domains\DomainVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SiteDomainController extends Controller
{
    /**
     * Connect a domain to the website. It starts unverified.
     */
    public function store(StoreSiteDomainRequest $request, Team $currentTeam, Site $site, UsageMeter $usage, SyncCloudflareHostname $cloudflare): RedirectResponse
    {
        $usage->ensureWithin($currentTeam, UsageMetric::Domains);

        $domain = $site->domains()->create([
            'hostname' => $request->validated('hostname'),
            'verification_token' => Str::random(32),
        ]);

        $cloudflare->syncSiteDomain($domain->fresh());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Domain added. Create the DNS records shown below (including the CNAME), then check them.')]);

        return back();
    }

    /**
     * Look up the domain's DNS records and verify it when they are in place.
     */
    public function verify(Team $currentTeam, Site $site, SiteDomain $domain, DomainVerifier $verifier): RedirectResponse
    {
        $verified = $verifier->verify($domain);
        $domain = $domain->fresh();

        Inertia::flash('toast', $verified
            ? ['type' => 'success', 'message' => $domain?->sslIsActive()
                ? __('Domain verified. HTTPS is active.')
                : __('Domain verified. HTTPS is activating — check again in a minute.')]
            : ['type' => 'error', 'message' => $domain?->check_error ?? __('The domain could not be verified.')]);

        return back();
    }

    /**
     * Disconnect a domain from the website.
     */
    public function destroy(Team $currentTeam, Site $site, SiteDomain $domain, SyncCloudflareHostname $cloudflare): RedirectResponse
    {
        $cloudflare->forgetSiteDomain($domain);
        $domain->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Domain removed.')]);

        return back();
    }
}

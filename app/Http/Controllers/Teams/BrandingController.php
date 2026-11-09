<?php

namespace App\Http\Controllers\Teams;

use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Models\MailConnection;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\UsageMeter;
use App\Services\Preview\TeamPreviewHostnameVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BrandingController extends Controller
{
    /**
     * Show the agency identity used on the portal, proposals and invoices.
     */
    public function edit(Request $request, Team $currentTeam, UsageMeter $usage, TeamPreviewHostnameVerifier $verifier): Response
    {
        $canUsePreviewDomain = $usage->allows($currentTeam, PlanFeature::WhiteLabel);
        $owner = $currentTeam->owner();
        $connection = $currentTeam->mailConnection;

        return Inertia::render('branding/Edit', [
            'canManage' => $request->user()->can('update', $currentTeam),
            'canUsePreviewDomain' => $canUsePreviewDomain,
            'accountEmail' => $currentTeam->contactEmail() ?? '',
            'accountEmailOwner' => $owner instanceof User ? $owner->name : '',
            'brand' => [
                'name' => $currentTeam->brand_name ?? '',
                'logoUrl' => $currentTeam->brand_logo_url ?? '',
                'color' => $currentTeam->brand_color ?? '#18181b',
                'email' => $currentTeam->contactEmail() ?? '',
            ],
            'mailbox' => [
                'imapSyncEnabled' => (bool) config('inbox.imap_sync_enabled'),
                'connected' => $connection instanceof MailConnection,
                'connection' => $connection instanceof MailConnection ? $connection->toSettingsArray() : null,
            ],
            'preview' => $currentTeam->brand(),
            'previewDomain' => $canUsePreviewDomain ? [
                'hostname' => $currentTeam->preview_hostname ?? '',
                'verified' => $currentTeam->hasVerifiedPreviewHostname(),
                'origin' => $currentTeam->brandedPreviewOrigin(),
                'checkError' => $currentTeam->preview_hostname_check_error,
                'dnsRecords' => filled($currentTeam->preview_hostname)
                    ? $verifier->instructions($currentTeam)
                    : [],
            ] : null,
        ]);
    }

    public function update(Request $request, Team $currentTeam): RedirectResponse
    {
        Gate::authorize('update', $currentTeam);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'logo_url' => ['nullable', 'url:https', 'max:500'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $currentTeam->forceFill([
            'brand_name' => $validated['name'] ?? null,
            'brand_logo_url' => $validated['logo_url'] ?? null,
            'brand_color' => $validated['color'] ?? null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branding saved.')]);

        return back();
    }
}

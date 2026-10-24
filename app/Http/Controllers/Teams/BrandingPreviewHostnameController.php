<?php

namespace App\Http\Controllers\Teams;

use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sites\StoreSiteDomainRequest;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use App\Services\Preview\TeamPreviewHostnameVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BrandingPreviewHostnameController extends Controller
{
    /**
     * Save the hostname agencies want for shareable preview links.
     */
    public function store(Request $request, Team $currentTeam, UsageMeter $usage, TeamPreviewHostnameVerifier $verifier): RedirectResponse
    {
        Gate::authorize('update', $currentTeam);
        $usage->ensureFeature($currentTeam, PlanFeature::WhiteLabel);

        $validated = $request->validate([
            'hostname' => ['required', 'string', 'max:253'],
        ]);

        $hostname = StoreSiteDomainRequest::normalize($validated['hostname']);

        if ($hostname === '') {
            throw ValidationException::withMessages(['hostname' => __('Enter a valid hostname.')]);
        }

        if (! $verifier->assign($currentTeam, $hostname)) {
            throw ValidationException::withMessages(['hostname' => __('That domain is already connected to a published website.')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Preview domain saved. Add the DNS records, then verify.')]);

        return back();
    }

    /**
     * Check DNS for the preview hostname.
     */
    public function verify(Team $currentTeam, UsageMeter $usage, TeamPreviewHostnameVerifier $verifier): RedirectResponse
    {
        Gate::authorize('update', $currentTeam);
        $usage->ensureFeature($currentTeam, PlanFeature::WhiteLabel);

        $verified = $verifier->verify($currentTeam);

        Inertia::flash('toast', $verified
            ? ['type' => 'success', 'message' => __('Preview domain verified. Share links now use this hostname.')]
            : ['type' => 'error', 'message' => $currentTeam->preview_hostname_check_error ?? __('The domain could not be verified.')]);

        return back();
    }

    /**
     * Remove the branded preview hostname.
     */
    public function destroy(Team $currentTeam, UsageMeter $usage): RedirectResponse
    {
        Gate::authorize('update', $currentTeam);
        $usage->ensureFeature($currentTeam, PlanFeature::WhiteLabel);

        $currentTeam->forceFill([
            'preview_hostname' => null,
            'preview_hostname_token' => null,
            'preview_hostname_verified_at' => null,
            'preview_hostname_checked_at' => null,
            'preview_hostname_check_error' => null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Preview domain removed.')]);

        return back();
    }
}

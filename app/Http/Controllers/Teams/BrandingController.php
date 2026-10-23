<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\Team;
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
    public function edit(Request $request, Team $currentTeam): Response
    {
        return Inertia::render('branding/Edit', [
            'canManage' => $request->user()->can('update', $currentTeam),
            'brand' => [
                'name' => $currentTeam->brand_name ?? '',
                'logoUrl' => $currentTeam->brand_logo_url ?? '',
                'color' => $currentTeam->brand_color ?? '#18181b',
                'email' => $currentTeam->brand_email ?? '',
            ],
            'preview' => $currentTeam->brand(),
        ]);
    }

    public function update(Request $request, Team $currentTeam): RedirectResponse
    {
        Gate::authorize('update', $currentTeam);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'logo_url' => ['nullable', 'url:https', 'max:500'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $currentTeam->forceFill([
            'brand_name' => $validated['name'] ?? null,
            'brand_logo_url' => $validated['logo_url'] ?? null,
            'brand_color' => $validated['color'] ?? null,
            'brand_email' => $validated['email'] ?? null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branding saved.')]);

        return back();
    }
}

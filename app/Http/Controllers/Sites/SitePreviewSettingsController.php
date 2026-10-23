<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SitePreviewSettingsController extends Controller
{
    /**
     * Set when the public preview expires and whether it needs a password.
     */
    public function update(Request $request, Team $currentTeam, Site $site): RedirectResponse
    {
        $data = $request->validate([
            'preview_expires_at' => ['nullable', 'date'],
            'preview_password' => ['nullable', 'string', 'min:4', 'max:100'],
            'clear_password' => ['sometimes', 'boolean'],
        ]);

        $site->forceFill(['preview_expires_at' => $data['preview_expires_at'] ?? null]);

        if ($request->boolean('clear_password')) {
            $site->preview_password = null;
        } elseif (filled($data['preview_password'] ?? null)) {
            $site->preview_password = $data['preview_password'];
        }

        $site->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Preview access updated.')]);

        return back();
    }
}

<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Jobs\TransferSiteOwnership;
use App\Models\Site;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SiteTransferController extends Controller
{
    /**
     * Unpublish the site on our hosting and email the client that they now own the files.
     */
    public function store(Request $request, Team $currentTeam, Site $site): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ]);

        TransferSiteOwnership::dispatchSync($site, $data['email']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transferred. The client has been emailed.')]);

        return back();
    }
}

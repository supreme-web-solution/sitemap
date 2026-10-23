<?php

namespace App\Http\Controllers\Discover;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateNicheBrief;
use App\Models\NicheBrief;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NicheResearchController extends Controller
{
    /**
     * Research briefs the team has generated.
     */
    public function index(Team $currentTeam): Response
    {
        return Inertia::render('research/Index', [
            'briefs' => $currentTeam->nicheBriefs()->latest('id')->limit(20)->get()->map(fn (NicheBrief $brief) => [
                'id' => $brief->id,
                'industry' => $brief->industry,
                'location' => $brief->location,
                'status' => $brief->status,
                'brief' => $brief->brief,
                'error' => $brief->error,
                'createdAt' => $brief->created_at->toISOString(),
            ]),
        ]);
    }

    /**
     * Queue a new research brief.
     */
    public function store(Request $request, Team $currentTeam): RedirectResponse
    {
        $data = $request->validate([
            'industry' => ['required', 'string', 'max:80'],
            'location' => ['required', 'string', 'max:120'],
        ]);

        $brief = $currentTeam->nicheBriefs()->create($data + [
            'user_id' => $request->user()->id,
            'status' => 'queued',
        ]);

        GenerateNicheBrief::dispatch($brief);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Research queued.')]);

        return back();
    }
}

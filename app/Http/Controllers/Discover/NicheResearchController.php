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
        $this->finishQueuedBriefs($currentTeam);

        return Inertia::render('research/Index', [
            'briefs' => $currentTeam->nicheBriefs()
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (NicheBrief $brief) => [
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

        GenerateNicheBrief::dispatchSync($brief);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brief ready — scroll down to use it.')]);

        return back();
    }

    /**
     * Rule-based briefs are instant; heal rows left "queued" when no worker was running.
     */
    protected function finishQueuedBriefs(Team $currentTeam): void
    {
        $currentTeam->nicheBriefs()
            ->where('status', 'queued')
            ->orderBy('id')
            ->each(fn (NicheBrief $brief) => GenerateNicheBrief::dispatchSync($brief));
    }
}

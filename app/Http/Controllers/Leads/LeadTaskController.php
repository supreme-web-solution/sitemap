<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LeadTaskController extends Controller
{
    /**
     * Add a task on this lead.
     */
    public function store(Request $request, Team $currentTeam, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'due_on' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', Rule::exists('team_members', 'user_id')->where('team_id', $currentTeam->id)],
        ]);

        $lead->tasks()->create($data + ['team_id' => $currentTeam->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task added.')]);

        return back();
    }

    /**
     * Mark a task done or still open.
     */
    public function update(Team $currentTeam, Lead $lead, LeadTask $task): RedirectResponse
    {
        $task->forceFill(['completed_at' => $task->completed_at ? null : now()])->save();

        return back();
    }

    /**
     * Delete a task.
     */
    public function destroy(Team $currentTeam, Lead $lead, LeadTask $task): RedirectResponse
    {
        $task->delete();

        return back();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminTeamController extends Controller
{
    /**
     * List teams so a plan can be assigned to each.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $planFilter = (string) $request->query('plan', '');

        $teams = Team::query()
            ->withCount(['members', 'leads', 'sites'])
            ->with('plan:id,name')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($planFilter === 'none', fn ($query) => $query->whereNull('plan_id'))
            ->when(ctype_digit($planFilter), fn ($query) => $query->where('plan_id', (int) $planFilter))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'is_personal' => $team->is_personal,
                'plan_id' => $team->plan_id,
                'owner' => $team->owner()?->email,
                'members_count' => $team->members_count,
                'leads_count' => $team->leads_count,
                'sites_count' => $team->sites_count,
            ]);

        return Inertia::render('admin/teams/Index', [
            'teams' => $teams,
            'filters' => ['search' => $search, 'plan' => $planFilter],
            'plans' => Plan::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'defaultPlan' => Plan::default()?->name,
        ]);
    }

    /**
     * Assign (or clear) a team's plan.
     */
    public function update(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['nullable', Rule::exists(Plan::class, 'id')],
        ]);

        $team->forceFill(['plan_id' => $validated['plan_id'] ?? null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan updated.')]);


        return back();
    }
}
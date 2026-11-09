<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     * Members for a team, loaded when an admin opens the members modal.
     */
    public function members(Team $team): JsonResponse
    {
        $members = $team->members()
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email', 'users.suspended_at', 'users.email_verified_at'])
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
                'suspended_at' => $member->suspended_at,
                'email_verified_at' => $member->email_verified_at,
                'is_admin' => $member->isAdmin(),
            ])
            ->values()
            ->all();

        return response()->json([
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
            ],
            'members' => $members,
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

    /**
     * Delete a team. Members with no other teams left are deleted too (except admins).
     */
    public function destroy(Team $team): RedirectResponse
    {
        DB::transaction(function () use ($team) {
            $memberIds = $team->members()->pluck('users.id');

            User::query()
                ->where('current_team_id', $team->id)
                ->each(function (User $user) use ($team) {
                    $fallback = $user->fallbackTeam($team);

                    if ($fallback) {
                        $user->switchTeam($fallback);

                        return;
                    }

                    $user->forceFill(['current_team_id' => null])->save();
                });

            $team->invitations()->delete();
            $team->memberships()->delete();
            $team->delete();

            User::query()
                ->whereIn('id', $memberIds)
                ->whereDoesntHave('teams')
                ->get()
                ->reject(fn (User $user) => $user->isAdmin())
                ->each(fn (User $user) => $user->delete());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team deleted.')]);

        return back();
    }
}

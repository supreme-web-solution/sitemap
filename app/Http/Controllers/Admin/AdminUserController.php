<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Teams\CreateTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveUserRequest;
use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use App\Services\Auth\UserRoleAssigner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly UserRoleAssigner $roles,
    ) {}

    /**
     * List users with search and a status filter.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $users = User::query()
            ->with(['roles:id,name', 'reseller:id,email', 'teams.plan:id,name'])
            ->withCount('teams')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($status === 'suspended', fn ($query) => $query->whereNotNull('suspended_at'))
            ->when($status === 'active', fn ($query) => $query->whereNull('suspended_at'))
            ->when($status === 'unverified', fn ($query) => $query->whereNull('email_verified_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->onEachSide(1)
            ->through(fn (User $user) => $this->present($user) + [
                'teams_count' => $user->teams_count,
                'teams' => $this->teamRows($user),
                'can_manage' => ! $user->is($request->user()) && ! $user->isAdmin(),
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'counts' => $this->userCounts(),
            'filters' => ['search' => $search, 'status' => $status],
            'plans' => $this->planOptions(),
            ...$this->roleProps(),
        ]);
    }

    /**
     * Create a user with a personal team, optionally on a plan.
     */
    public function store(SaveUserRequest $request, CreateTeam $createTeam): RedirectResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated, $createTeam) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $user->forceFill(['email_verified_at' => ($validated['verified'] ?? true) ? now() : null])->save();

            $team = $createTeam->handle($user, $user->name."'s Team", isPersonal: true);
            $team->forceFill(['plan_id' => $validated['plan_id'] ?? null])->save();

            if (array_key_exists('roles', $validated)) {
                $this->roles->syncRoles($user, $validated['roles'] ?? []);
            }

            return $user;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('admin.users.index');
    }

    /**
     * Show a user's account, teams and actions.
     */
    public function edit(User $user): Response
    {
        $user->load(['roles:id,name', 'reseller:id,email']);

        $user->load(['teams.plan:id,name']);

        return Inertia::render('admin/users/Edit', [
            'account' => $this->present($user),
            'teams' => $this->teamRows($user),
            'plans' => $this->planOptions(),
            ...$this->roleProps(),
        ]);
    }

    /**
     * Update a user's profile, password and verification.
     */
    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'email_verified_at' => ($validated['verified'] ?? false) ? ($user->email_verified_at ?? now()) : null,
        ]);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        if (array_key_exists('roles', $validated)) {
            $this->roles->syncRoles($user, $validated['roles'] ?? []);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return back();
    }

    /**
     * Block a user from signing in.
     */
    public function suspend(Request $request, User $user): RedirectResponse
    {
        if ($this->isProtected($request, $user)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You cannot suspend this account.')]);

            return back();
        }

        $user->forceFill(['suspended_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User suspended.')]);

        return back();
    }

    /**
     * Let a suspended user sign in again.
     */
    public function restore(User $user): RedirectResponse
    {
        $user->forceFill(['suspended_at' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User reactivated.')]);

        return back();
    }

    /**
     * Delete a user, along with teams they were the only member of.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($this->isProtected($request, $user)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You cannot delete this account.')]);

            return back();
        }

        DB::transaction(function () use ($user) {
            $user->teams()->get()
                ->filter(fn (Team $team) => $team->members()->count() === 1)
                ->each(fn (Team $team) => $team->delete());

            $user->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('admin.users.index');
    }

    /**
     * Administrators and the person signed in cannot be suspended or deleted from here.
     */
    private function isProtected(Request $request, User $user): bool
    {
        return $user->is($request->user()) || $user->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'suspended_at' => $user->suspended_at,
            'created_at' => $user->created_at,
            'is_admin' => $user->isAdmin(),
            'roles' => $user->relationLoaded('roles') ? $user->roles->pluck('name')->values()->all() : [],
            'reseller_email' => $user->relationLoaded('reseller') ? $user->reseller?->email : null,
        ];
    }

    /**
     * @return list<array{id: int, name: string, slug: string, is_personal: bool, role: string, plan_id: int|null, plan_name: string|null}>
     */
    private function teamRows(User $user): array
    {
        return $user->teams->map(fn (Team $team) => [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'is_personal' => $team->is_personal,
            'role' => $team->pivot->role,
            'plan_id' => $team->plan_id,
            'plan_name' => $team->plan?->name,
        ])->values()->all();
    }

    /**
     * @return array{rolesEnabled: bool, assignableRoles: list<string>}
     */
    private function roleProps(): array
    {
        return [
            'rolesEnabled' => $this->roles->rolesEnabled(),
            'assignableRoles' => $this->roles->assignableRoles(),
        ];
    }

    /**
     * @return array{total: int, active: int, suspended: int, unverified: int}
     */
    private function userCounts(): array
    {
        $counts = User::query()->toBase()->selectRaw(
            'count(*) as total, sum(case when suspended_at is null then 1 else 0 end) as active, sum(case when suspended_at is not null then 1 else 0 end) as suspended, sum(case when email_verified_at is null then 1 else 0 end) as unverified',
        )->first();

        return [
            'total' => (int) ($counts->total ?? 0),
            'active' => (int) ($counts->active ?? 0),
            'suspended' => (int) ($counts->suspended ?? 0),
            'unverified' => (int) ($counts->unverified ?? 0),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function planOptions(): array
    {
        return Plan::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])->map->only(['id', 'name'])->all();
    }
}

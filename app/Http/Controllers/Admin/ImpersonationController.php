<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ImpersonationController extends Controller
{
    /**
     * Sign in as another user, remembering who the administrator is.
     */
    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();

        if ($user->is($admin) || $user->isAdmin()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You cannot impersonate this account.')]);

            return back();
        }

        Auth::login($user);
        $request->session()->put('impersonator_id', $admin->id);
        $request->session()->regenerate();

        $team = $user->currentTeam ?? $user->personalTeam();

        return $team
            ? redirect()->route('dashboard', ['current_team' => $team->slug])
            : redirect('/');
    }

    /**
     * Go back to the administrator's own account.
     */
    public function stop(Request $request): RedirectResponse
    {
        $admin = User::query()->find($request->session()->pull('impersonator_id'));

        if ($admin === null || ! $admin->isAdmin()) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login');
        }

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('admin.users.index');
    }
}
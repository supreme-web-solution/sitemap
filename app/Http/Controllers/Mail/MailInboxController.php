<?php

namespace App\Http\Controllers\Mail;

use App\Enums\MailConnectionProvider;
use App\Enums\MailConnectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mail\ConnectMailConnectionRequest;
use App\Jobs\SyncTeamMailbox;
use App\Models\Team;
use App\Services\Mail\MailConnectionManager;
use App\Services\Mail\MailConnectionUnavailableException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MailInboxController extends Controller
{
    /**
     * Old inbox URL. Mailbox setup now lives on Branding.
     */
    public function index(Team $currentTeam): RedirectResponse
    {
        return redirect()->route('branding.edit', [
            'current_team' => $currentTeam->slug,
            'mailbox' => 1,
        ]);
    }

    /**
     * Save or update mailbox credentials for the team.
     */
    public function store(ConnectMailConnectionRequest $request, Team $currentTeam, MailConnectionManager $manager): RedirectResponse
    {
        $existing = $currentTeam->mailConnection;
        $credentials = $request->credentials($existing?->credentials);

        if ($request->validated('provider') === MailConnectionProvider::Imap->value && blank($credentials['password'] ?? null)) {
            throw ValidationException::withMessages([
                'imap_password' => __('Enter the mailbox password.'),
            ]);
        }

        $connection = $currentTeam->mailConnection()->updateOrCreate([], [
            'provider' => $request->validated('provider'),
            'email_address' => $request->validated('email_address'),
            'credentials' => $credentials,
            'status' => MailConnectionStatus::Pending,
            'last_sync_error' => null,
        ]);

        if ($connection->syncAvailable()) {
            try {
                $manager->client($connection)->verify($connection);
                $connection->forceFill(['status' => MailConnectionStatus::Connected])->save();
                SyncTeamMailbox::dispatch($connection);
            } catch (MailConnectionUnavailableException $exception) {
                $connection->forceFill([
                    'status' => MailConnectionStatus::Error,
                    'last_sync_error' => Str::limit($exception->getMessage(), 240),
                ])->save();

                throw ValidationException::withMessages([
                    'email_address' => $exception->getMessage(),
                ]);
            }
        } else {
            $connection->forceFill(['status' => MailConnectionStatus::Connected])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inbox settings saved.')]);

        return back();
    }

    /**
     * Queue a manual sync for the connected mailbox.
     */
    public function sync(Team $currentTeam): RedirectResponse
    {
        Gate::authorize('update', $currentTeam);

        $connection = $currentTeam->mailConnection;

        if ($connection === null || ! $connection->syncAvailable()) {
            abort(404);
        }

        SyncTeamMailbox::dispatch($connection);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inbox sync queued.')]);

        return back();
    }

    /**
     * Disconnect the mailbox. Imported replies stay on leads.
     */
    public function destroy(Team $currentTeam): RedirectResponse
    {
        Gate::authorize('update', $currentTeam);

        $currentTeam->mailConnection()?->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inbox disconnected.')]);

        return back();
    }
}

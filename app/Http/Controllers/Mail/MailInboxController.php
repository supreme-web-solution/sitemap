<?php

namespace App\Http\Controllers\Mail;

use App\Enums\MailConnectionProvider;
use App\Enums\MailConnectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mail\ConnectMailConnectionRequest;
use App\Jobs\SyncTeamMailbox;
use App\Models\MailConnection;
use App\Models\Team;
use App\Services\Mail\MailConnectionManager;
use App\Services\Mail\MailConnectionUnavailableException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MailInboxController extends Controller
{
    /**
     * Show the team's connected outreach reply mailbox.
     */
    public function index(Request $request, Team $currentTeam): Response
    {
        $connection = $currentTeam->mailConnection;
        $canManage = $request->user()->can('update', $currentTeam);

        return Inertia::render('mail/Index', [
            'canManage' => $canManage,
            'imapSyncEnabled' => (bool) config('inbox.imap_sync_enabled'),
            'connected' => $connection !== null,
            'connection' => $connection === null ? null : $this->connectionPayload($connection),
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

    /**
     * @return array<string, mixed>
     */
    protected function connectionPayload(MailConnection $connection): array
    {
        $credentials = $connection->credentials ?? [];

        return [
            'provider' => $connection->provider->value,
            'providerLabel' => $connection->provider->label(),
            'emailAddress' => $connection->email_address,
            'status' => $connection->status->value,
            'statusLabel' => $connection->status->label(),
            'syncAvailable' => $connection->syncAvailable(),
            'lastSyncAt' => $connection->last_sync_at?->toISOString(),
            'lastSyncError' => $connection->last_sync_error,
            'imapHost' => $credentials['host'] ?? null,
            'imapPort' => isset($credentials['port']) ? (int) $credentials['port'] : null,
            'imapEncryption' => $credentials['encryption'] ?? null,
            'imapUsername' => $credentials['username'] ?? null,
        ];
    }
}

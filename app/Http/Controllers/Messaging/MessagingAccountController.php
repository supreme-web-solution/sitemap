<?php

namespace App\Http\Controllers\Messaging;

use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use App\Services\Messaging\MessagingException;
use App\Services\Messaging\ZernioClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MessagingAccountController extends Controller
{
    /**
     * Show the team's SMS and WhatsApp connection.
     */
    public function index(Request $request, Team $currentTeam, UsageMeter $usage): Response
    {
        $account = $currentTeam->messagingAccount;
        $canManage = $request->user()->can('update', $currentTeam);

        return Inertia::render('messaging/Index', [
            'canManage' => $canManage,
            'available' => $usage->allows($currentTeam, PlanFeature::Messaging),
            'connected' => $account !== null,
            'account' => $account === null ? null : [
                'smsFrom' => $account->sms_from,
                'whatsappAccountId' => $account->whatsapp_account_id,
                'timezone' => $account->timezone,
                'quietStartHour' => $account->quiet_start_hour,
                'quietEndHour' => $account->quiet_end_hour,
                'webhookUrl' => route('webhooks.zernio', $account->webhook_key),
                'webhookSecret' => $canManage ? $account->webhook_secret : null,
            ],
        ]);
    }

    /**
     * Connect with the team's own Zernio key, or update the saved settings.
     */
    public function store(Request $request, Team $currentTeam, UsageMeter $usage): RedirectResponse
    {
        Gate::authorize('update', $currentTeam);
        $usage->ensureFeature($currentTeam, PlanFeature::Messaging);

        $existing = $currentTeam->messagingAccount;

        $data = $request->validate([
            'api_key' => [$existing === null ? 'required' : 'nullable', 'string', 'max:200'],
            'sms_from' => ['nullable', 'string', 'max:20'],
            'whatsapp_account_id' => ['nullable', 'string', 'max:60'],
            'timezone' => ['required', 'timezone:all'],
            'quiet_start_hour' => ['required', 'integer', 'between:0,23'],
            'quiet_end_hour' => ['required', 'integer', 'between:0,23'],
        ]);

        if (filled($data['api_key'] ?? null)) {
            try {
                (new ZernioClient($data['api_key']))->verify();
            } catch (MessagingException $exception) {
                throw ValidationException::withMessages(['api_key' => $exception->getMessage()]);
            }
        } else {
            unset($data['api_key']);
        }

        $currentTeam->messagingAccount()->updateOrCreate([], $data + [
            'webhook_key' => $existing->webhook_key ?? Str::random(32),
            'webhook_secret' => $existing->webhook_secret ?? Str::random(40),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Messaging settings saved.')]);

        return back();
    }

    /**
     * Disconnect. Past conversations stay on each lead.
     */
    public function destroy(Team $currentTeam): RedirectResponse
    {
        Gate::authorize('update', $currentTeam);

        $currentTeam->messagingAccount()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Messaging disconnected.')]);

        return back();
    }
}

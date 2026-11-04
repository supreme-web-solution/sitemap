<?php

namespace App\Http\Controllers\Messaging;

use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use App\Services\Messaging\MessagingException;
use App\Services\Messaging\ZernioClient;
use App\Support\PhoneNumber;
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
            'defaultTimezone' => config('app.timezone'),
            'account' => $account === null ? null : [
                'phone' => $account->sms_from,
                'whatsappReady' => filled($account->whatsapp_account_id),
                'timezone' => $account->timezone,
                'quietStartHour' => $account->quiet_start_hour,
                'quietEndHour' => $account->quiet_end_hour,
            ],
        ]);
    }

    /**
     * Connect the team's business number for SMS and WhatsApp (when linked on the provider).
     */
    public function store(Request $request, Team $currentTeam, UsageMeter $usage): RedirectResponse
    {
        Gate::authorize('update', $currentTeam);
        $usage->ensureFeature($currentTeam, PlanFeature::Messaging);

        if (! ZernioClient::usesPlatformKey()) {
            throw ValidationException::withMessages([
                'phone' => __('Messaging is not available right now. Try again later.'),
            ]);
        }

        $existing = $currentTeam->messagingAccount;

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'timezone' => ['required', 'timezone:all'],
            'quiet_start_hour' => ['required', 'integer', 'between:0,23'],
            'quiet_end_hour' => ['required', 'integer', 'between:0,23'],
        ]);

        $e164 = PhoneNumber::e164($data['phone']);

        if ($e164 === null) {
            throw ValidationException::withMessages([
                'phone' => __('Use a full number with country code, e.g. +15551234567.'),
            ]);
        }

        try {
            $client = ZernioClient::platform();
            $client->verify();
            $link = $client->linkSendingNumber($e164);
        } catch (MessagingException $exception) {
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        }

        $currentTeam->messagingAccount()->updateOrCreate([], [
            'sms_from' => $link['sms_from'],
            'whatsapp_account_id' => $link['whatsapp_account_id'],
            'timezone' => $data['timezone'],
            'quiet_start_hour' => $data['quiet_start_hour'],
            'quiet_end_hour' => $data['quiet_end_hour'],
            'webhook_key' => $existing->webhook_key ?? Str::random(32),
            'webhook_secret' => $existing->webhook_secret ?? Str::random(40),
        ]);

        $message = filled($link['whatsapp_account_id'])
            ? __('Messaging connected for SMS and WhatsApp.')
            : __('Messaging connected for SMS.');

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

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

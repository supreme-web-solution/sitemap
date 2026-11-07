<?php

namespace App\Http\Controllers\Payments;

use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\ConnectPaymentAccountRequest;
use App\Models\PaymentAccount;
use App\Models\Team;
use App\Services\Payments\PaymentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PaymentAccountController extends Controller
{
    /**
     * Show the payment providers the team has connected.
     */
    public function index(Request $request, Team $currentTeam): Response
    {
        $accounts = $currentTeam->paymentAccounts()->get()->keyBy(fn (PaymentAccount $account) => $account->provider->value);

        return Inertia::render('payments/Index', [
            'canManage' => $request->user()->can('update', $currentTeam),
            'providers' => collect(PaymentProvider::cases())->map(function (PaymentProvider $provider) use ($accounts) {
                /** @var PaymentAccount|null $account */
                $account = $accounts->get($provider->value);

                return [
                    'value' => $provider->value,
                    'label' => $provider->label(),
                    'connected' => $account !== null,
                    'enabled' => (bool) ($account?->enabled ?? false),
                    'accountLabel' => $account?->label,
                    'hint' => $account?->hint(),
                    'mode' => $account?->credential('mode'),
                    'hasWebhookSecret' => $account?->credential($provider->webhookSecretKey()) !== null,
                    'webhookUrl' => $account
                        ? route($provider->webhookRoute(), $account->webhook_key)
                        : null,
                ];
            })->values(),
        ]);
    }

    /**
     * Connect a provider with the team's own API credentials, or replace the ones saved.
     */
    public function store(ConnectPaymentAccountRequest $request, Team $currentTeam, PaymentProvider $provider): RedirectResponse
    {
        Gate::authorize('update', $currentTeam);

        $existing = $currentTeam->paymentAccounts()->where('provider', $provider)->first();
        $credentials = array_filter($request->validated(), fn ($value) => filled($value));

        $webhookKey = $provider->webhookSecretKey();

        if (! isset($credentials[$webhookKey]) && $existing?->credential($webhookKey)) {
            $credentials[$webhookKey] = $existing->credential($webhookKey);
        }

        try {
            $label = $provider->gateway()->verifyCredentials($credentials);
        } catch (PaymentException $exception) {
            throw ValidationException::withMessages(['credentials' => $exception->getMessage()]);
        }

        $currentTeam->paymentAccounts()->updateOrCreate(
            ['provider' => $provider],
            [
                'credentials' => $credentials,
                'label' => $label,
                'webhook_key' => $existing?->webhook_key ?? Str::random(32),
                // New connections stay off until the owner turns them on for invoices.
                'enabled' => $existing?->enabled ?? false,
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $existing
                ? __(':provider keys updated.', ['provider' => $provider->label()])
                : __(':provider connected. Turn it on to offer it on invoices.', ['provider' => $provider->label()]),
        ]);

        return back();
    }

    /**
     * Offer or hide a connected provider on public invoice pay links.
     */
    public function update(Request $request, Team $currentTeam, PaymentProvider $provider): RedirectResponse
    {
        Gate::authorize('update', $currentTeam);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $account = $currentTeam->paymentAccounts()->where('provider', $provider)->firstOrFail();
        $account->forceFill(['enabled' => $data['enabled']])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $data['enabled']
                ? __(':provider is on for invoices.', ['provider' => $provider->label()])
                : __(':provider is hidden from invoices.', ['provider' => $provider->label()]),
        ]);

        return back();
    }

    /**
     * Disconnect a provider. Invoices already paid are unaffected.
     */
    public function destroy(Team $currentTeam, PaymentProvider $provider): RedirectResponse
    {
        Gate::authorize('update', $currentTeam);

        $currentTeam->paymentAccounts()->where('provider', $provider)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':provider disconnected.', ['provider' => $provider->label()])]);

        return back();
    }
}

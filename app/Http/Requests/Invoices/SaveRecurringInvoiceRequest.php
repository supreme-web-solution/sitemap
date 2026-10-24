<?php

namespace App\Http\Requests\Invoices;

use App\Enums\BillingInterval;
use App\Enums\PaymentProvider;
use App\Enums\RecurringCollectionMode;
use App\Http\Requests\Concerns\HasInvoiceItems;
use App\Models\RecurringInvoice;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveRecurringInvoiceRequest extends FormRequest
{
    use HasInvoiceItems;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The client, interval and start date are fixed once a schedule exists.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('current_team');
        $schedule = $this->route('recurringInvoice');
        $isNew = ! $schedule instanceof RecurringInvoice;

        return [
            ...($isNew ? [
                'lead_id' => ['required', 'integer', Rule::exists('leads', 'id')->where('team_id', $team->id)],
                'interval' => ['required', Rule::enum(BillingInterval::class)],
                'starts_on' => ['required', 'date', 'after_or_equal:today'],
            ] : []),
            'ends_on' => ['nullable', 'date', 'after_or_equal:'.($isNew ? 'starts_on' : $schedule->starts_on->toDateString())],
            'due_in_days' => ['required', 'integer', 'min:0', 'max:90'],
            'email_client' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...($isNew ? [
                'collection_mode' => ['required', Rule::enum(RecurringCollectionMode::class)],
                'payment_provider' => [
                    'nullable',
                    Rule::enum(PaymentProvider::class),
                    Rule::requiredIf(fn () => $this->input('collection_mode') === RecurringCollectionMode::AutoCharge->value),
                ],
            ] : []),
            ...$this->itemRules(),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateMinimumTotal($validator),
            function (Validator $validator) {
                if ($this->input('collection_mode') !== RecurringCollectionMode::AutoCharge->value) {
                    return;
                }

                /** @var Team $team */
                $team = $this->route('current_team');
                $provider = PaymentProvider::tryFrom((string) $this->input('payment_provider'));

                if ($provider === null || ! in_array($provider, [PaymentProvider::Stripe, PaymentProvider::PayPal], true)) {
                    $validator->errors()->add(
                        'payment_provider',
                        __('Choose Stripe or PayPal for automatic charging.'),
                    );

                    return;
                }

                if (! $team->paymentAccounts()->where('provider', $provider)->exists()) {
                    $validator->errors()->add(
                        'collection_mode',
                        __('Connect :provider on the Payments page before enabling automatic charging.', [
                            'provider' => $provider->label(),
                        ]),
                    );
                }
            },
        ];
    }
}

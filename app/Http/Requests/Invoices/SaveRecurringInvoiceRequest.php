<?php

namespace App\Http\Requests\Invoices;

use App\Enums\BillingInterval;
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
            ...$this->itemRules(),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateMinimumTotal($validator)];
    }
}

<?php

namespace App\Http\Requests\Invoices;

use App\Enums\InvoiceKind;
use App\Http\Requests\Concerns\HasInvoiceItems;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveInvoiceRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('current_team');

        return [
            'lead_id' => ['required', 'integer', Rule::exists('leads', 'id')->where('team_id', $team->id)],
            'kind' => ['nullable', Rule::enum(InvoiceKind::class)],
            'due_on' => ['nullable', 'date'],
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

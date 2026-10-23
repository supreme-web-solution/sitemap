<?php

namespace App\Http\Requests\Proposals;

use App\Enums\BillingInterval;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProposalRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('current_team');

        return [
            'lead_id' => ['required', 'integer', Rule::exists('leads', 'id')->where('team_id', $team->id)],
            'title' => ['required', 'string', 'max:200'],
            'intro' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.description' => ['required', 'string', 'max:200'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0.01', 'max:999999.99', 'decimal:0,2'],
            'items.*.interval' => ['nullable', Rule::enum(BillingInterval::class)],
        ];
    }

    /**
     * The line items ready to be stored.
     *
     * @return list<array{description: string, quantity: int, unit_cents: int, interval: string|null}>
     */
    public function lineItems(): array
    {
        return array_values(array_map(fn (array $item) => [
            'description' => $item['description'],
            'quantity' => (int) $item['quantity'],
            'unit_cents' => (int) round(((float) $item['unit_price']) * 100),
            'interval' => $item['interval'] ?? null,
        ], $this->validated('items')));
    }
}

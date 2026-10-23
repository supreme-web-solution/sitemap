<?php

namespace App\Http\Requests\Concerns;

use App\Models\Invoice;
use Illuminate\Validation\Validator;

trait HasInvoiceItems
{
    /**
     * The validation rules for the line items.
     *
     * @return array<string, list<string>>
     */
    protected function itemRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.description' => ['required', 'string', 'max:200'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0.01', 'max:999999.99', 'decimal:0,2'],
        ];
    }

    /**
     * Reject totals that card processors will not charge.
     */
    protected function validateMinimumTotal(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if ($this->totalCents() < Invoice::MINIMUM_CENTS) {
            $validator->errors()->add('items', __('The invoice total must be at least :amount.', ['amount' => Invoice::formatCents(Invoice::MINIMUM_CENTS)]));
        }
    }

    /**
     * The invoice total in cents.
     */
    public function totalCents(): int
    {
        return (int) collect($this->validated('items') ?? $this->input('items', []))
            ->sum(fn (array $item) => (int) $item['quantity'] * $this->toCents($item['unit_price']));
    }

    /**
     * The line items ready to be stored.
     *
     * @return list<array{description: string, quantity: int, unit_cents: int}>
     */
    public function lineItems(): array
    {
        return array_values(array_map(fn (array $item) => [
            'description' => $item['description'],
            'quantity' => (int) $item['quantity'],
            'unit_cents' => $this->toCents($item['unit_price']),
        ], $this->validated('items')));
    }

    protected function toCents(mixed $dollars): int
    {
        return (int) round(((float) $dollars) * 100);
    }
}

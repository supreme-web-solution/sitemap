<?php

namespace Database\Factories;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'team_id' => fn (array $attributes) => Lead::find($attributes['lead_id'])->team_id,
            'number' => fn (array $attributes) => 'INV-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => InvoiceStatus::Open,
            'kind' => InvoiceKind::Standard,
            'total_cents' => 150000,
            'sent_at' => now(),
        ];
    }

    /**
     * Give the invoice a single $1,500 line item.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Invoice $invoice) {
            if ($invoice->items()->doesntExist()) {
                $invoice->items()->save(new InvoiceItem(['description' => 'Website design and build', 'quantity' => 1, 'unit_cents' => $invoice->total_cents]));
            }
        });
    }

    /**
     * Indicate that the invoice has not been sent yet.
     */
    public function draft(): static
    {
        return $this->state(fn () => ['status' => InvoiceStatus::Draft, 'sent_at' => null]);
    }

    /**
     * Indicate that the invoice has been paid.
     */
    public function paid(): static
    {
        return $this->state(fn () => ['status' => InvoiceStatus::Paid, 'paid_at' => now(), 'paid_via' => 'manual']);
    }
}

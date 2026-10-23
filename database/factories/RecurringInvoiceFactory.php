<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Models\Lead;
use App\Models\RecurringInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringInvoice>
 */
class RecurringInvoiceFactory extends Factory
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
            'interval' => BillingInterval::Monthly,
            'starts_on' => today(),
            'next_run_on' => fn (array $attributes) => $attributes['starts_on'],
            'due_in_days' => 14,
            'email_client' => true,
            'is_active' => true,
            'items' => [['description' => 'Website hosting and maintenance', 'quantity' => 1, 'unit_cents' => 5000]],
        ];
    }

    /**
     * Pause the schedule.
     */
    public function paused(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

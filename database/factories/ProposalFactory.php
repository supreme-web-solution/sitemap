<?php

namespace Database\Factories;

use App\Enums\ProposalStatus;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\ProposalItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposal>
 */
class ProposalFactory extends Factory
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
            'number' => fn () => 'PROP-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'title' => 'Website and hosting proposal',
            'status' => ProposalStatus::Sent,
            'sent_at' => now(),
        ];
    }

    /**
     * Give the proposal a $1,500 build and $50 a month hosting.
     */
    public function withDefaultItems(): static
    {
        return $this->afterCreating(function (Proposal $proposal) {
            $proposal->items()->saveMany([
                new ProposalItem(['description' => 'Website design and build', 'quantity' => 1, 'unit_cents' => 150000]),
                new ProposalItem(['description' => 'Hosting and maintenance', 'quantity' => 1, 'unit_cents' => 5000, 'interval' => 'monthly']),
            ]);
        });
    }
}
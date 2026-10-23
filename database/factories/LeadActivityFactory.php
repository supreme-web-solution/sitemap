<?php

namespace Database\Factories;

use App\Enums\LeadActivityType;
use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadActivity>
 */
class LeadActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => fn (array $attributes) => Lead::find($attributes['lead_id'])->team_id,
            'lead_id' => Lead::factory(),
            'type' => LeadActivityType::Note,
            'summary' => fake()->sentence(),
        ];
    }
}

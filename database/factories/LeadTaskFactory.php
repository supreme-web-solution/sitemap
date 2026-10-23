<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadTask>
 */
class LeadTaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'team_id' => fn (array $attributes) => Lead::find($attributes['lead_id'])->team_id,
            'title' => fake()->sentence(4),
            'due_on' => today()->addDay(),
        ];
    }
}

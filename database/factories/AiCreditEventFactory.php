<?php

namespace Database\Factories;

use App\Models\AiCreditEvent;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiCreditEvent>
 */
class AiCreditEventFactory extends Factory
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
            'team_id' => fn (array $attributes) => Lead::query()->find($attributes['lead_id'])?->team_id,
            'provider' => 'anthropic',
            'source' => 'site_creation',
            'message' => 'AI provider [anthropic] has insufficient credits or quota.',
            'outcome' => 'Finished with built-in copy',
            'occurred_at' => now(),
            'finished_at' => now(),
        ];
    }
}

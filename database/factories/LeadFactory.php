<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'place_id' => 'ChIJ'.fake()->unique()->bothify('????????##########'),
            'name' => fake()->company(),
            'category' => 'Plumber',
            'phone' => fake()->phoneNumber(),
            'website' => null,
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'country' => 'US',
            'rating' => fake()->randomFloat(1, 3.5, 5),
            'reviews_count' => fake()->numberBetween(5, 400),
            'opening_hours' => null,
            'status' => LeadStatus::New,
        ];
    }

    /**
     * Indicate that the lead already has a website.
     */
    public function withWebsite(): static
    {
        return $this->state(fn (array $attributes) => [
            'website' => fake()->url(),
        ]);
    }
}

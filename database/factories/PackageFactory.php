<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
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
            'name' => fake()->words(3, true),
            'description' => null,
            'unit_cents' => 50000,
            'interval' => null,
            'is_active' => true,
        ];
    }
}
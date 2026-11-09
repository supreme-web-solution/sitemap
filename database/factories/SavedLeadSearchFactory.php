<?php

namespace Database\Factories;

use App\Enums\WebsiteFilter;
use App\Models\SavedLeadSearch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedLeadSearch>
 */
class SavedLeadSearchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true),
            'keyword' => fake()->randomElement(['plumbers', 'dentists', 'window repair']),
            'location' => fake()->city().', USA',
            'website_filter' => WebsiteFilter::WithoutWebsite,
            'minimum_rating' => null,
            'max_results' => 20,
        ];
    }
}

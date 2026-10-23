<?php

namespace Database\Factories;

use App\Enums\LeadSearchStatus;
use App\Enums\WebsiteFilter;
use App\Models\LeadSearch;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadSearch>
 */
class LeadSearchFactory extends Factory
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
            'keyword' => fake()->randomElement(['plumbers', 'dentists', 'restaurants', 'hair salons']),
            'location' => fake()->city().', USA',
            'website_filter' => WebsiteFilter::WithoutWebsite,
            'minimum_rating' => null,
            'max_results' => 20,
            'status' => LeadSearchStatus::Pending,
        ];
    }

    /**
     * Indicate that the search is waiting on the provider.
     */
    public function running(string $providerJobId = 'job-123'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeadSearchStatus::Running,
            'provider_job_id' => $providerJobId,
        ]);
    }

    /**
     * Indicate that the search has completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeadSearchStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}

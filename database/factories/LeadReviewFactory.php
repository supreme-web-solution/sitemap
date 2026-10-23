<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadReview;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LeadReview>
 */
class LeadReviewFactory extends Factory
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
            'external_id' => Str::random(40),
            'author_name' => fake()->name(),
            'rating' => 5,
            'body' => fake()->sentence(14),
            'posted_at_text' => fake()->randomElement(['a week ago', '2 months ago', 'a year ago']),
            'is_visible' => true,
        ];
    }

    /**
     * Indicate that the review is hidden from the website.
     */
    public function hidden(): static
    {
        return $this->state(fn () => ['is_visible' => false]);
    }
}

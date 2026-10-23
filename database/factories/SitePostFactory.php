<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SitePost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SitePost>
 */
class SitePostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(5);

        return [
            'site_id' => Site::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(14),
            'body' => implode("\n\n", fake()->paragraphs(3)),
            'published_at' => now()->subDay(),
        ];
    }

    /**
     * Indicate that the post is still a draft.
     */
    public function draft(): static
    {
        return $this->state(fn () => ['published_at' => null]);
    }
}

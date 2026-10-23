<?php

namespace Database\Factories;

use App\Models\PreviewComment;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreviewComment>
 */
class PreviewCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'author_name' => fake()->name(),
            'author_contact' => fake()->email(),
            'section' => 'about',
            'body' => fake()->sentence(),
        ];
    }
}

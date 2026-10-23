<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteChatMessage>
 */
class SiteChatMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'role' => SiteChatMessage::ROLE_USER,
            'body' => fake()->sentence(),
            'status' => SiteChatMessage::STATUS_DONE,
        ];
    }
}

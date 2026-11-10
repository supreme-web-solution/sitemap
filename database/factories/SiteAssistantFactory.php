<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteAssistant;
use App\Services\Assistant\AssistantVoices;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SiteAssistant>
 */
class SiteAssistantFactory extends Factory
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
            'team_id' => fn (array $attributes) => Site::find($attributes['site_id'])->team_id,
            'public_key' => Str::random(40),
            'enabled' => false,
            'name' => 'Maya',
            'presenter_id' => 'v2_public_amber@abc',
            'presenter_name' => 'Amber',
            'presenter_gender' => 'female',
            'presenter_thumbnail_url' => 'https://cdn.example.com/amber.jpg',
            'voice_id' => AssistantVoices::DEFAULT,
            'voice_name' => 'Nova',
            'talking_avatar_enabled' => false,
            'simli_face_id' => null,
            'simli_face_name' => null,
            'launcher_side' => 'right',
            'greeting' => null,
            'knowledge' => "Business: Example Plumbing\nServices: Drain cleaning, leak repair",
            'knowledge_generated_at' => now(),
        ];
    }

    /**
     * Indicate that the assistant is switched on for the website.
     */
    public function enabled(): static
    {
        return $this->state(fn () => ['enabled' => true]);
    }
}

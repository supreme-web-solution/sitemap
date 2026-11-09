<?php

namespace Database\Factories;

use App\Enums\PitchVideoStatus;
use App\Models\Site;
use App\Models\SitePitchVideo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SitePitchVideo>
 */
class SitePitchVideoFactory extends Factory
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
            'public_token' => Str::random(40),
            'status' => PitchVideoStatus::Draft,
            'script' => 'Hi, this is a quick video about the website we built for your business.',
            'presenter_id' => 'v2_public_amber@Y5K02DLS4m',
            'presenter_name' => 'Amber',
            'presenter_gender' => 'female',
            'presenter_thumbnail_url' => 'https://example.com/amber.jpg',
            'voice_id' => 'en-US-JennyNeural',
            'voice_name' => 'Jenny',
            'voice_provider' => 'microsoft',
        ];
    }

    /**
     * Indicate that the video has been rendered and stored.
     */
    public function ready(): static
    {
        return $this->state(fn () => [
            'status' => PitchVideoStatus::Ready,
            'did_clip_id' => 'clp_'.Str::random(10),
            'video_path' => 'teams/1/pitch-videos/'.Str::lower(Str::random(10)).'.mp4',
            'generated_at' => now(),
        ]);
    }

    /**
     * Indicate that D-ID is still rendering the video.
     */
    public function generating(): static
    {
        return $this->state(fn () => [
            'status' => PitchVideoStatus::Generating,
            'did_clip_id' => 'clp_'.Str::random(10),
            'generation_started_at' => now(),
        ]);
    }
}

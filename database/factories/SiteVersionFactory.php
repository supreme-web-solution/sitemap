<?php

namespace Database\Factories;

use App\Enums\SiteTemplate;
use App\Models\Site;
use App\Models\SiteVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteVersion>
 */
class SiteVersionFactory extends Factory
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
            'template' => SiteTemplate::ModernClean,
            'content' => ['headline' => fake()->sentence()],
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\SiteSection;
use App\Enums\SiteTemplate;
use App\Models\Lead;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
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
            'team_id' => fn (array $attributes) => Lead::find($attributes['lead_id'])->team_id,
            'slug' => Str::lower(Str::random(12)),
            'template' => SiteTemplate::ModernClean,
            'content' => [
                'accent' => '#2563eb',
                'headline' => 'Reliable Plumbing When You Need It Most',
                'tagline' => 'Fast. Friendly. Local.',
                'cta_label' => 'Get a Free Estimate',
                'about' => 'We are a local team serving our community.',
                'sections' => SiteSection::defaultOrder(),
                'services' => [
                    ['title' => 'Emergency Repairs', 'description' => 'Fast help when something goes wrong.'],
                ],
                'faqs' => [],
            ],
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\SiteSection;
use App\Enums\SiteTemplate;
use App\Models\Lead;
use App\Models\SiteConcept;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SiteConcept>
 */
class SiteConceptFactory extends Factory
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
            'batch' => Str::random(12),
            'template' => SiteTemplate::ModernClean,
            'content' => [
                'accent' => '#2563eb',
                'headline' => fake()->sentence(4),
                'tagline' => 'Fast. Friendly. Local.',
                'cta_label' => 'Get in touch',
                'about' => 'We are a local team serving our community.',
                'sections' => SiteSection::defaultOrder(),
                'services' => [['title' => 'Repairs', 'description' => 'Quick, tidy work.']],
                'faqs' => [],
            ],
        ];
    }
}

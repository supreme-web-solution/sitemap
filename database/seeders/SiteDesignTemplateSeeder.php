<?php

namespace Database\Seeders;

use App\Enums\SiteDesignStyle;
use App\Enums\SiteLayoutVariant;
use App\Enums\SiteTemplate;
use App\Models\SiteDesignTemplate;
use Illuminate\Database\Seeder;

class SiteDesignTemplateSeeder extends Seeder
{
    /**
     * Seed the searchable design catalog (each row maps to one of the three Blade themes).
     */
    public function run(): void
    {
        if (SiteDesignTemplate::query()->exists()) {
            return;
        }

        $entries = [
            [
                'slug' => 'modern-clean-pro',
                'name' => 'Modern Clean Pro',
                'base_template' => SiteTemplate::ModernClean,
                'style' => SiteDesignStyle::Modern,
                'industries' => ['consulting', 'legal', 'accounting', 'technology'],
                'accent_color' => '#2563eb',
                'description' => 'Crisp layout for professional services that need trust at a glance.',
                'layout_variant' => SiteLayoutVariant::SplitHero,
                'sort_order' => 10,
            ],
            [
                'slug' => 'modern-minimal-clinic',
                'name' => 'Modern Minimal Clinic',
                'base_template' => SiteTemplate::ModernClean,
                'style' => SiteDesignStyle::Minimal,
                'industries' => ['dental', 'medical', 'healthcare', 'therapy'],
                'accent_color' => '#0d9488',
                'description' => 'Calm spacing and teal accents for care-focused practices.',
                'sort_order' => 20,
            ],
            [
                'slug' => 'modern-trade-services',
                'name' => 'Modern Trade Services',
                'base_template' => SiteTemplate::ModernClean,
                'style' => SiteDesignStyle::Modern,
                'industries' => ['plumbing', 'hvac', 'electrician', 'roofing', 'landscaping'],
                'accent_color' => '#16a34a',
                'description' => 'Direct headlines and strong CTAs for home and trade businesses.',
                'layout_variant' => SiteLayoutVariant::CardServices,
                'sort_order' => 30,
            ],
            [
                'slug' => 'warm-studio-local',
                'name' => 'Warm Studio Local',
                'base_template' => SiteTemplate::WarmStudio,
                'style' => SiteDesignStyle::Warm,
                'industries' => ['restaurant', 'cafe', 'bakery', 'catering'],
                'accent_color' => '#c2410c',
                'description' => 'Inviting typography and earthy colour for hospitality.',
                'sort_order' => 40,
            ],
            [
                'slug' => 'warm-wellness-spa',
                'name' => 'Warm Wellness Spa',
                'base_template' => SiteTemplate::WarmStudio,
                'style' => SiteDesignStyle::Warm,
                'industries' => ['spa', 'salon', 'beauty', 'yoga', 'fitness'],
                'accent_color' => '#be123c',
                'description' => 'Soft sections and warm accents for wellness brands.',
                'sort_order' => 50,
            ],
            [
                'slug' => 'warm-boutique-retail',
                'name' => 'Warm Boutique Retail',
                'base_template' => SiteTemplate::WarmStudio,
                'style' => SiteDesignStyle::Elegant,
                'industries' => ['retail', 'boutique', 'florist', 'jewelry'],
                'accent_color' => '#a16207',
                'description' => 'Gallery-forward layout for shops that sell by atmosphere.',
                'sort_order' => 60,
            ],
            [
                'slug' => 'editorial-dark-creative',
                'name' => 'Editorial Dark Creative',
                'base_template' => SiteTemplate::EditorialDark,
                'style' => SiteDesignStyle::Bold,
                'industries' => ['photography', 'design', 'creative agency', 'video production'],
                'accent_color' => '#f59e0b',
                'description' => 'High-contrast hero and bold type for visual portfolios.',
                'layout_variant' => SiteLayoutVariant::EditorialHero,
                'sort_order' => 70,
            ],
            [
                'slug' => 'editorial-dark-nightlife',
                'name' => 'Editorial Dark Nightlife',
                'base_template' => SiteTemplate::EditorialDark,
                'style' => SiteDesignStyle::Bold,
                'industries' => ['bar', 'nightclub', 'entertainment', 'events'],
                'accent_color' => '#e11d48',
                'description' => 'Dramatic dark canvas with punchy accent colour.',
                'sort_order' => 80,
            ],
            [
                'slug' => 'editorial-dark-luxury',
                'name' => 'Editorial Dark Luxury',
                'base_template' => SiteTemplate::EditorialDark,
                'style' => SiteDesignStyle::Elegant,
                'industries' => ['real estate', 'architecture', 'luxury services', 'automotive'],
                'accent_color' => '#38bdf8',
                'description' => 'Editorial rhythm with cool accent for premium brands.',
                'sort_order' => 90,
            ],
        ];

        foreach ($entries as $entry) {
            SiteDesignTemplate::query()->create([
                ...$entry,
                'base_template' => $entry['base_template']->value,
                'style' => $entry['style']->value,
                'layout_variant' => ($entry['layout_variant'] ?? SiteLayoutVariant::Standard)->value,
                'is_active' => true,
            ]);
        }
    }
}

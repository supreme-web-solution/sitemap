<?php

namespace Database\Seeders;

use App\Enums\SiteDesignStyle;
use App\Enums\SiteLayoutVariant;
use App\Enums\SiteTemplate;
use App\Models\Site;
use App\Models\SiteDesignTemplate;
use App\Services\SiteBuilder\SectionVariants;
use Illuminate\Database\Seeder;

class SiteDesignTemplateSeeder extends Seeder
{
    /**
     * Seed the searchable design catalog.
     *
     * Each row renders with one of the three Blade themes and picks a layout variant per
     * section (hero, services, about, gallery, FAQ), so rows on the same theme still look
     * clearly different. Missing rows are added and rows without variants get them; rows an
     * admin already changed are left alone.
     */
    public function run(): void
    {
        $existing = SiteDesignTemplate::query()->get(['id', 'slug', 'variants'])->keyBy('slug');

        foreach (self::entries() as $entry) {
            $row = $existing->get($entry['slug']);
            $desired = SectionVariants::withDefaults(array_merge(
                SectionVariants::complementary($entry['variants']),
                $entry['variants'],
            ));

            if ($row === null) {
                SiteDesignTemplate::query()->create([
                    ...$entry,
                    'base_template' => $entry['base_template']->value,
                    'style' => $entry['style']->value,
                    'layout_variant' => ($entry['layout_variant'] ?? SiteLayoutVariant::Standard)->value,
                    'variants' => $desired,
                    'is_active' => true,
                ]);

                continue;
            }
            $current = $row->variants ?? [];

            if ($current === []) {
                $row->update(['variants' => $desired]);

                continue;
            }

            $merged = array_merge($desired, SectionVariants::clean($current));
            $services = $entry['variants']['services'] ?? '';
            $replacingAutomaticStack = in_array($services, ['list', 'numbered'], true)
                && ($current['services_page'] ?? null) === 'stack';

            if ($replacingAutomaticStack) {
                $merged['services_page'] = 'zigzag';
            }

            if ($merged !== $current) {
                $row->update(['variants' => $merged]);
            }

            if ($replacingAutomaticStack) {
                $this->alternateServicesPages($row->id);
            }
        }
    }

    /**
     * Move websites that inherited the old all-right services page onto the alternating layout.
     *
     * A later choice of "Image on the right" is stored on the site and is left as it is
     * once the template itself no longer carries the automatic stack value.
     */
    protected function alternateServicesPages(int $designTemplateId): void
    {
        Site::query()
            ->where('design_template_id', $designTemplateId)
            ->each(function (Site $site): void {
                $content = $site->content;
                $variants = $content['variants'] ?? null;

                if (! is_array($variants) || ($variants['services_page'] ?? null) !== 'stack') {
                    return;
                }

                $variants['services_page'] = 'zigzag';
                $content['variants'] = $variants;
                $site->content = $content;
                $site->save();
            });
    }

    /**
     * The starter catalog.
     *
     * @return list<array{slug: string, name: string, base_template: SiteTemplate, style: SiteDesignStyle, industries: list<string>, accent_color: string, description: string, layout_variant?: SiteLayoutVariant, variants: array<string, string>, sort_order: int}>
     */
    public static function entries(): array
    {
        return [
            // Modern Clean (bright, professional).
            [
                'slug' => 'modern-clean-pro',
                'name' => 'Modern Clean Pro',
                'base_template' => SiteTemplate::ModernClean,
                'style' => SiteDesignStyle::Modern,
                'industries' => ['consulting', 'legal', 'accounting', 'technology'],
                'accent_color' => '#2563eb',
                'description' => 'Crisp layout for professional services that need trust at a glance.',
                'layout_variant' => SiteLayoutVariant::SplitHero,
                'variants' => ['hero' => 'split', 'services' => 'cards', 'about' => 'split', 'gallery' => 'grid', 'faq' => 'accordion'],
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
                'variants' => ['hero' => 'minimal', 'services' => 'list', 'about' => 'centered', 'gallery' => 'grid', 'faq' => 'lined'],
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
                'variants' => ['hero' => 'overlay', 'services' => 'featured', 'about' => 'card', 'gallery' => 'mosaic', 'faq' => 'two-column'],
                'sort_order' => 30,
            ],
            [
                'slug' => 'modern-legal-trust',
                'name' => 'Modern Legal Trust',
                'base_template' => SiteTemplate::ModernClean,
                'style' => SiteDesignStyle::Modern,
                'industries' => ['law firm', 'attorney', 'insurance', 'financial advisor', 'tax'],
                'accent_color' => '#0284c7',
                'description' => 'Centered headline, numbered practice areas and a confident pull quote.',
                'variants' => ['hero' => 'centered', 'services' => 'numbered', 'about' => 'quote', 'gallery' => 'grid', 'faq' => 'numbered'],
                'sort_order' => 100,
            ],
            [
                'slug' => 'modern-home-pros',
                'name' => 'Modern Home Pros',
                'base_template' => SiteTemplate::ModernClean,
                'style' => SiteDesignStyle::Modern,
                'industries' => ['cleaning', 'maid service', 'pest control', 'moving', 'handyman', 'painting'],
                'accent_color' => '#0891b2',
                'description' => 'Photo banner, friendly tiles and a bold brand-colour about band.',
                'variants' => ['hero' => 'photo-top', 'services' => 'tiles', 'about' => 'highlight', 'gallery' => 'masonry', 'faq' => 'cards'],
                'sort_order' => 110,
            ],
            [
                'slug' => 'modern-auto-garage',
                'name' => 'Modern Auto Garage',
                'base_template' => SiteTemplate::ModernClean,
                'style' => SiteDesignStyle::Bold,
                'industries' => ['auto repair', 'mechanic', 'car wash', 'tires', 'towing'],
                'accent_color' => '#ca8a04',
                'description' => 'Full-photo hero with open service columns and a swipeable work strip.',
                'variants' => ['hero' => 'overlay', 'services' => 'columns', 'about' => 'columns', 'gallery' => 'strip', 'faq' => 'lined'],
                'sort_order' => 120,
            ],
            [
                'slug' => 'modern-learning-centre',
                'name' => 'Modern Learning Centre',
                'base_template' => SiteTemplate::ModernClean,
                'style' => SiteDesignStyle::Modern,
                'industries' => ['tutoring', 'school', 'daycare', 'coaching', 'training'],
                'accent_color' => '#16a34a',
                'description' => 'Split hero, outlined programmes and large photo pairs for families.',
                'variants' => ['hero' => 'split', 'services' => 'outline', 'about' => 'centered', 'gallery' => 'duo', 'faq' => 'cards'],
                'sort_order' => 130,
            ],
            [
                'slug' => 'modern-tech-studio',
                'name' => 'Modern Tech Studio',
                'base_template' => SiteTemplate::ModernClean,
                'style' => SiteDesignStyle::Minimal,
                'industries' => ['software', 'it services', 'marketing agency', 'web design', 'saas'],
                'accent_color' => '#2563eb',
                'description' => 'Big type, numbered capabilities and a mosaic of recent work.',
                'variants' => ['hero' => 'minimal', 'services' => 'numbered', 'about' => 'columns', 'gallery' => 'mosaic', 'faq' => 'two-column'],
                'sort_order' => 140,
            ],

            // Warm Studio (soft, friendly).
            [
                'slug' => 'warm-studio-local',
                'name' => 'Warm Studio Local',
                'base_template' => SiteTemplate::WarmStudio,
                'style' => SiteDesignStyle::Warm,
                'industries' => ['restaurant', 'cafe', 'bakery', 'catering'],
                'accent_color' => '#c2410c',
                'description' => 'Inviting typography and earthy colour for hospitality.',
                'variants' => ['hero' => 'classic', 'services' => 'cards', 'about' => 'split', 'gallery' => 'polaroid', 'faq' => 'accordion'],
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
                'variants' => ['hero' => 'centered', 'services' => 'tiles', 'about' => 'centered', 'gallery' => 'masonry', 'faq' => 'lined'],
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
                'variants' => ['hero' => 'card', 'services' => 'outline', 'about' => 'card', 'gallery' => 'mosaic', 'faq' => 'cards'],
                'sort_order' => 60,
            ],
            [
                'slug' => 'warm-pet-care',
                'name' => 'Warm Pet Care',
                'base_template' => SiteTemplate::WarmStudio,
                'style' => SiteDesignStyle::Warm,
                'industries' => ['pet grooming', 'veterinarian', 'dog training', 'kennel', 'pet sitting'],
                'accent_color' => '#15803d',
                'description' => 'Friendly split hero, soft tiles and snapshot-style photos.',
                'variants' => ['hero' => 'split', 'services' => 'tiles', 'about' => 'highlight', 'gallery' => 'polaroid', 'faq' => 'numbered'],
                'sort_order' => 150,
            ],
            [
                'slug' => 'warm-home-living',
                'name' => 'Warm Home Living',
                'base_template' => SiteTemplate::WarmStudio,
                'style' => SiteDesignStyle::Elegant,
                'industries' => ['interior design', 'furniture', 'garden centre', 'nursery', 'home decor'],
                'accent_color' => '#a16207',
                'description' => 'Photo banner, one featured service and a calm pull quote.',
                'variants' => ['hero' => 'photo-top', 'services' => 'featured', 'about' => 'quote', 'gallery' => 'strip', 'faq' => 'two-column'],
                'sort_order' => 160,
            ],
            [
                'slug' => 'warm-weddings-events',
                'name' => 'Warm Weddings & Events',
                'base_template' => SiteTemplate::WarmStudio,
                'style' => SiteDesignStyle::Elegant,
                'industries' => ['wedding', 'event planner', 'party rental', 'venue', 'photographer'],
                'accent_color' => '#be123c',
                'description' => 'Centered romantic headline with polaroid galleries.',
                'variants' => ['hero' => 'centered', 'services' => 'columns', 'about' => 'quote', 'gallery' => 'polaroid', 'faq' => 'lined'],
                'sort_order' => 170,
            ],
            [
                'slug' => 'warm-community-hub',
                'name' => 'Warm Community Hub',
                'base_template' => SiteTemplate::WarmStudio,
                'style' => SiteDesignStyle::Warm,
                'industries' => ['church', 'nonprofit', 'community centre', 'charity', 'club'],
                'accent_color' => '#7c2d12',
                'description' => 'Welcoming full-photo hero with simple rows and large photo pairs.',
                'variants' => ['hero' => 'overlay', 'services' => 'list', 'about' => 'centered', 'gallery' => 'duo', 'faq' => 'accordion'],
                'sort_order' => 180,
            ],
            [
                'slug' => 'warm-barber-hair',
                'name' => 'Warm Barber & Hair',
                'base_template' => SiteTemplate::WarmStudio,
                'style' => SiteDesignStyle::Bold,
                'industries' => ['barber', 'hair salon', 'nails', 'lash', 'brow'],
                'accent_color' => '#b45309',
                'description' => 'Photo-led hero with numbered menu of services and a mosaic of styles.',
                'variants' => ['hero' => 'overlay', 'services' => 'numbered', 'about' => 'columns', 'gallery' => 'mosaic', 'faq' => 'lined'],
                'sort_order' => 190,
            ],

            // Editorial Dark (high contrast, premium).
            [
                'slug' => 'editorial-dark-creative',
                'name' => 'Editorial Dark Creative',
                'base_template' => SiteTemplate::EditorialDark,
                'style' => SiteDesignStyle::Bold,
                'industries' => ['photography', 'design', 'creative agency', 'video production'],
                'accent_color' => '#f59e0b',
                'description' => 'High-contrast hero and bold type for visual portfolios.',
                'layout_variant' => SiteLayoutVariant::EditorialHero,
                'variants' => ['hero' => 'classic', 'services' => 'cards', 'about' => 'split', 'gallery' => 'mosaic', 'faq' => 'accordion'],
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
                'variants' => ['hero' => 'centered', 'services' => 'list', 'about' => 'quote', 'gallery' => 'strip', 'faq' => 'lined'],
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
                'variants' => ['hero' => 'split', 'services' => 'columns', 'about' => 'card', 'gallery' => 'duo', 'faq' => 'numbered'],
                'sort_order' => 90,
            ],
            [
                'slug' => 'editorial-dark-gym',
                'name' => 'Editorial Dark Gym',
                'base_template' => SiteTemplate::EditorialDark,
                'style' => SiteDesignStyle::Bold,
                'industries' => ['gym', 'crossfit', 'martial arts', 'boxing', 'personal trainer'],
                'accent_color' => '#dc2626',
                'description' => 'Huge type, numbered programmes and a gritty masonry wall.',
                'variants' => ['hero' => 'minimal', 'services' => 'numbered', 'about' => 'columns', 'gallery' => 'masonry', 'faq' => 'two-column'],
                'sort_order' => 200,
            ],
            [
                'slug' => 'editorial-dark-ink-studio',
                'name' => 'Editorial Dark Ink Studio',
                'base_template' => SiteTemplate::EditorialDark,
                'style' => SiteDesignStyle::Bold,
                'industries' => ['tattoo', 'barbershop', 'piercing', 'streetwear'],
                'accent_color' => '#e11d48',
                'description' => 'Text card over a dark photo with outlined services and polaroid work.',
                'variants' => ['hero' => 'card', 'services' => 'outline', 'about' => 'highlight', 'gallery' => 'polaroid', 'faq' => 'cards'],
                'sort_order' => 210,
            ],
            [
                'slug' => 'editorial-dark-detailing',
                'name' => 'Editorial Dark Detailing',
                'base_template' => SiteTemplate::EditorialDark,
                'style' => SiteDesignStyle::Elegant,
                'industries' => ['car detailing', 'custom cars', 'motorcycle', 'wrap', 'paint protection'],
                'accent_color' => '#38bdf8',
                'description' => 'Photo banner, soft tiles and a showroom mosaic.',
                'variants' => ['hero' => 'photo-top', 'services' => 'tiles', 'about' => 'quote', 'gallery' => 'mosaic', 'faq' => 'lined'],
                'sort_order' => 220,
            ],
            [
                'slug' => 'editorial-dark-defense',
                'name' => 'Editorial Dark Defense',
                'base_template' => SiteTemplate::EditorialDark,
                'style' => SiteDesignStyle::Bold,
                'industries' => ['criminal defense', 'security', 'private investigator', 'bail bonds'],
                'accent_color' => '#b45309',
                'description' => 'Serious centered headline with numbered practice areas.',
                'variants' => ['hero' => 'centered', 'services' => 'numbered', 'about' => 'split', 'gallery' => 'grid', 'faq' => 'numbered'],
                'sort_order' => 230,
            ],
            [
                'slug' => 'editorial-dark-music',
                'name' => 'Editorial Dark Music',
                'base_template' => SiteTemplate::EditorialDark,
                'style' => SiteDesignStyle::Bold,
                'industries' => ['music', 'recording studio', 'dj', 'band', 'podcast'],
                'accent_color' => '#f59e0b',
                'description' => 'Full-bleed photo hero, one featured offer and a swipeable reel.',
                'variants' => ['hero' => 'overlay', 'services' => 'featured', 'about' => 'centered', 'gallery' => 'strip', 'faq' => 'numbered'],
                'sort_order' => 240,
            ],

            // Professional layouts modelled on the local-business homepages we want to match.
            [
                'slug' => 'professional-advisor',
                'name' => 'Professional Advisor',
                'base_template' => SiteTemplate::ModernClean,
                'style' => SiteDesignStyle::Modern,
                'industries' => ['accounting', 'cpa', 'tax', 'bookkeeping', 'financial advisor'],
                'accent_color' => '#0d9488',
                'description' => 'White professional homepage: numbered services, how-it-works steps, lined FAQ and a column footer.',
                'variants' => ['hero' => 'split', 'services' => 'numbered', 'about' => 'split', 'process' => 'steps', 'why' => 'numbered', 'gallery' => 'grid', 'areas' => 'chips', 'faq' => 'lined', 'footer' => 'columns'],
                'sort_order' => 250,
            ],
            [
                'slug' => 'professional-contractor',
                'name' => 'Professional Contractor',
                'base_template' => SiteTemplate::ModernClean,
                'style' => SiteDesignStyle::Modern,
                'industries' => ['general contractor', 'construction', 'remodeling', 'builder'],
                'accent_color' => '#2563eb',
                'description' => 'Photo overlay hero, numbered scopes, timeline process and a wide column footer.',
                'variants' => ['hero' => 'overlay', 'services' => 'numbered', 'about' => 'split', 'process' => 'timeline', 'why' => 'cards', 'gallery' => 'mosaic', 'areas' => 'chips', 'faq' => 'lined', 'footer' => 'mega'],
                'sort_order' => 260,
            ],
            [
                'slug' => 'professional-salon',
                'name' => 'Professional Salon',
                'base_template' => SiteTemplate::WarmStudio,
                'style' => SiteDesignStyle::Warm,
                'industries' => ['salon', 'beauty', 'hair salon', 'nails', 'spa'],
                'accent_color' => '#be123c',
                'description' => 'Warm photo-led homepage with service tiles, a mosaic gallery and city chips.',
                'variants' => ['hero' => 'photo-top', 'services' => 'tiles', 'about' => 'split', 'process' => 'steps', 'why' => 'cards', 'gallery' => 'mosaic', 'areas' => 'chips', 'faq' => 'accordion', 'footer' => 'columns'],
                'sort_order' => 270,
            ],
            [
                'slug' => 'professional-wellness',
                'name' => 'Professional Wellness',
                'base_template' => SiteTemplate::WarmStudio,
                'style' => SiteDesignStyle::Warm,
                'industries' => ['psychic', 'therapy', 'wellness', 'life coach', 'holistic'],
                'accent_color' => '#7c2d12',
                'description' => 'Soft overlay hero, pull-quote about, polaroid gallery and a stacked process.',
                'variants' => ['hero' => 'overlay', 'services' => 'cards', 'about' => 'quote', 'process' => 'stacked', 'why' => 'list', 'gallery' => 'polaroid', 'areas' => 'list', 'faq' => 'lined', 'footer' => 'columns'],
                'sort_order' => 280,
            ],
            [
                'slug' => 'professional-cleaning',
                'name' => 'Professional Cleaning',
                'base_template' => SiteTemplate::ModernClean,
                'style' => SiteDesignStyle::Modern,
                'industries' => ['cleaning', 'maid service', 'janitorial', 'house cleaning'],
                'accent_color' => '#0891b2',
                'description' => 'Clear service cards, step cards for the process, and a compact hours footer.',
                'variants' => ['hero' => 'split', 'services' => 'cards', 'about' => 'card', 'process' => 'cards', 'why' => 'list', 'gallery' => 'grid', 'areas' => 'grid', 'faq' => 'cards', 'footer' => 'columns'],
                'sort_order' => 290,
            ],
            [
                'slug' => 'professional-detailing',
                'name' => 'Professional Detailing',
                'base_template' => SiteTemplate::ModernClean,
                'style' => SiteDesignStyle::Bold,
                'industries' => ['car detailing', 'auto detailing', 'mobile detailing', 'car wash'],
                'accent_color' => '#0284c7',
                'description' => 'Full-photo hero, one featured package and a call bar in the footer.',
                'variants' => ['hero' => 'overlay', 'services' => 'featured', 'about' => 'highlight', 'process' => 'steps', 'why' => 'cards', 'gallery' => 'duo', 'areas' => 'chips', 'faq' => 'lined', 'footer' => 'cta'],
                'sort_order' => 300,
            ],
            [
                'slug' => 'professional-van-tech',
                'name' => 'Professional Van Tech',
                'base_template' => SiteTemplate::ModernClean,
                'style' => SiteDesignStyle::Minimal,
                'industries' => ['van conversion', 'rv', 'solar', 'electrical', 'camper'],
                'accent_color' => '#16a34a',
                'description' => 'Split hero, open service columns and a split process for technical shops.',
                'variants' => ['hero' => 'split', 'services' => 'columns', 'about' => 'columns', 'process' => 'split', 'why' => 'split', 'gallery' => 'strip', 'areas' => 'split', 'faq' => 'two-column', 'footer' => 'mega'],
                'sort_order' => 310,
            ],
            [
                'slug' => 'professional-insurance',
                'name' => 'Professional Insurance',
                'base_template' => SiteTemplate::ModernClean,
                'style' => SiteDesignStyle::Modern,
                'industries' => ['insurance', 'life insurance', 'annuity', 'medicare', 'financial advisor'],
                'accent_color' => '#2563eb',
                'description' => 'Centered headline, numbered products, stacked process and city chips.',
                'variants' => ['hero' => 'centered', 'services' => 'numbered', 'about' => 'split', 'process' => 'stacked', 'why' => 'numbered', 'gallery' => 'grid', 'areas' => 'chips', 'faq' => 'lined', 'footer' => 'columns'],
                'sort_order' => 320,
            ],
            [
                'slug' => 'professional-home-auto',
                'name' => 'Professional Home & Auto',
                'base_template' => SiteTemplate::ModernClean,
                'style' => SiteDesignStyle::Bold,
                'industries' => ['handyman', 'home repair', 'auto repair', 'painting', 'landscaping'],
                'accent_color' => '#ca8a04',
                'description' => 'Overlay hero, service tiles, stacked process and a mosaic of recent jobs.',
                'variants' => ['hero' => 'overlay', 'services' => 'tiles', 'about' => 'card', 'process' => 'stacked', 'why' => 'cards', 'gallery' => 'mosaic', 'areas' => 'grid', 'faq' => 'accordion', 'footer' => 'columns'],
                'sort_order' => 330,
            ],
            [
                'slug' => 'professional-roofing',
                'name' => 'Professional Roofing',
                'base_template' => SiteTemplate::ModernClean,
                'style' => SiteDesignStyle::Bold,
                'industries' => ['roofing', 'roof repair', 'storm damage', 'gutter', 'siding'],
                'accent_color' => '#9a3412',
                'description' => 'Conversion-first overlay, featured service, call-bar footer and lined FAQ.',
                'variants' => ['hero' => 'overlay', 'services' => 'featured', 'about' => 'highlight', 'process' => 'steps', 'why' => 'band', 'gallery' => 'duo', 'areas' => 'chips', 'faq' => 'lined', 'footer' => 'cta'],
                'sort_order' => 340,
            ],
        ];
    }
}

<?php

namespace App\Services\SiteBuilder;

use App\Data\SiteDraft;
use App\Enums\ReviewsStyle;
use App\Enums\SiteSection;
use App\Enums\SiteTemplate;
use App\Models\Lead;
use Illuminate\Support\Str;

class RuleBasedCopywriter implements SiteCopywriter
{
    /**
     * Industry presets matched against the lead's category.
     *
     * @var array<string, array{
     *     keywords: list<string>,
     *     noun: string,
     *     template: SiteTemplate,
     *     accent: string,
     *     cta: string,
     *     promise: string,
     *     services: list<array{0: string, 1: string}>,
     * }>
     */
    protected const PRESETS = [
        'plumbing' => [
            'keywords' => ['plumb'],
            'noun' => 'plumbing',
            'template' => SiteTemplate::ModernClean,
            'accent' => '#2563eb',
            'cta' => 'Get a Free Estimate',
            'promise' => 'Fast, honest plumbing help from a local team.',
            'services' => [
                ['Repairs', 'Leaks, clogs and broken fixtures handled quickly and cleanly.'],
                ['Drain Cleaning', 'Clear slow or blocked drains and keep water flowing.'],
                ['Water Heaters', 'Repair and replacement to get your hot water back.'],
                ['Installations', 'New fixtures, pipes and appliances fitted properly.'],
            ],
        ],
        'dental' => [
            'keywords' => ['dent', 'orthodont'],
            'noun' => 'dental care',
            'template' => SiteTemplate::ModernClean,
            'accent' => '#0d9488',
            'cta' => 'Book an Appointment',
            'promise' => 'Gentle, modern dental care for the whole family.',
            'services' => [
                ['Checkups & Cleanings', 'Routine care that keeps your smile healthy.'],
                ['Cosmetic Dentistry', 'Treatments that help you feel confident in your smile.'],
                ['Restorative Care', 'Fillings, crowns and repairs done with care.'],
                ['Urgent Visits', 'Get seen when tooth pain cannot wait.'],
            ],
        ],
        'restaurant' => [
            'keywords' => ['restaurant', 'cafe', 'coffee', 'pizza', 'grill', 'bakery', 'bar', 'food', 'kitchen', 'diner', 'bistro'],
            'noun' => 'food',
            'template' => SiteTemplate::EditorialDark,
            'accent' => '#f59e0b',
            'cta' => 'Visit Us Today',
            'promise' => 'Good food, warm service and a place you will want to come back to.',
            'services' => [
                ['Dine In', 'Relax and enjoy your meal with us.'],
                ['Takeaway', 'Your favourites, ready when you are.'],
                ['Group Bookings', 'Bring friends, family or colleagues together.'],
            ],
        ],
        'beauty' => [
            'keywords' => ['salon', 'hair', 'barber', 'beauty', 'nail', 'spa', 'lash', 'brow'],
            'noun' => 'beauty',
            'template' => SiteTemplate::WarmStudio,
            'accent' => '#be123c',
            'cta' => 'Book Your Visit',
            'promise' => 'Look and feel your best with a team that listens.',
            'services' => [
                ['Cuts & Styling', 'Looks tailored to you and your lifestyle.'],
                ['Colour', 'From subtle refreshes to bold new shades.'],
                ['Treatments', 'Care that leaves you feeling refreshed.'],
                ['Special Occasions', 'Be ready for the moments that matter.'],
            ],
        ],
        'electrical' => [
            'keywords' => ['electric'],
            'noun' => 'electrical services',
            'template' => SiteTemplate::ModernClean,
            'accent' => '#ca8a04',
            'cta' => 'Request a Quote',
            'promise' => 'Safe, reliable electrical work done right the first time.',
            'services' => [
                ['Repairs', 'Find and fix electrical faults safely.'],
                ['Lighting', 'Indoor and outdoor lighting installed to suit your space.'],
                ['Upgrades', 'Panels, outlets and wiring brought up to date.'],
                ['Inspections', 'Peace of mind for homes and businesses.'],
            ],
        ],
        'automotive' => [
            'keywords' => ['auto', 'mechanic', 'car ', 'tire', 'tyre', 'garage', 'body shop'],
            'noun' => 'auto repair',
            'template' => SiteTemplate::EditorialDark,
            'accent' => '#dc2626',
            'cta' => 'Book a Service',
            'promise' => 'Straightforward car care that keeps you on the road.',
            'services' => [
                ['Diagnostics', 'Find out exactly what your vehicle needs.'],
                ['Brakes', 'Inspections and repairs for safe stopping.'],
                ['Maintenance', 'Oil changes and routine servicing.'],
                ['Repairs', 'Quality repairs explained in plain language.'],
            ],
        ],
        'cleaning' => [
            'keywords' => ['clean', 'maid', 'janitor'],
            'noun' => 'cleaning',
            'template' => SiteTemplate::ModernClean,
            'accent' => '#0891b2',
            'cta' => 'Get a Free Quote',
            'promise' => 'Spotless spaces without the hassle.',
            'services' => [
                ['Home Cleaning', 'Regular cleaning on a schedule that suits you.'],
                ['Deep Cleaning', 'A thorough top-to-bottom clean.'],
                ['Move In / Move Out', 'Leave or arrive to a fresh, clean space.'],
                ['Commercial', 'Clean, welcoming workplaces.'],
            ],
        ],
        'hvac' => [
            'keywords' => ['hvac', 'heating', 'air condition', 'cooling'],
            'noun' => 'heating and cooling',
            'template' => SiteTemplate::ModernClean,
            'accent' => '#0284c7',
            'cta' => 'Schedule Service',
            'promise' => 'Comfortable indoor temperatures all year round.',
            'services' => [
                ['AC Repair', 'Get cool air flowing again.'],
                ['Heating Repair', 'Stay warm when it matters most.'],
                ['Installations', 'Efficient systems sized for your space.'],
                ['Maintenance', 'Tune-ups that prevent breakdowns.'],
            ],
        ],
        'roofing' => [
            'keywords' => ['roof'],
            'noun' => 'roofing',
            'template' => SiteTemplate::ModernClean,
            'accent' => '#9a3412',
            'cta' => 'Get a Free Inspection',
            'promise' => 'Protect your home with roofing work you can trust.',
            'services' => [
                ['Roof Repairs', 'Fix leaks and damage before they spread.'],
                ['Replacements', 'Durable new roofs installed with care.'],
                ['Inspections', 'Know the condition of your roof.'],
                ['Gutters', 'Keep water moving away from your home.'],
            ],
        ],
        'landscaping' => [
            'keywords' => ['landscap', 'lawn', 'garden', 'tree'],
            'noun' => 'landscaping',
            'template' => SiteTemplate::ModernClean,
            'accent' => '#16a34a',
            'cta' => 'Get a Free Quote',
            'promise' => 'Outdoor spaces that look great all season.',
            'services' => [
                ['Lawn Care', 'Mowing, edging and regular upkeep.'],
                ['Garden Design', 'Planting and layouts that fit your space.'],
                ['Hardscaping', 'Paths, patios and borders.'],
                ['Cleanups', 'Seasonal tidy-ups and debris removal.'],
            ],
        ],
        'legal' => [
            'keywords' => ['law', 'attorney', 'lawyer', 'legal', 'solicitor'],
            'noun' => 'legal services',
            'template' => SiteTemplate::EditorialDark,
            'accent' => '#b45309',
            'cta' => 'Request a Consultation',
            'promise' => 'Clear advice and committed representation.',
            'services' => [
                ['Consultations', 'Talk through your situation and options.'],
                ['Representation', 'Someone in your corner when it counts.'],
                ['Documents', 'Agreements and paperwork prepared carefully.'],
            ],
        ],
        'fitness' => [
            'keywords' => ['gym', 'fitness', 'yoga', 'pilates', 'crossfit', 'martial'],
            'noun' => 'fitness',
            'template' => SiteTemplate::EditorialDark,
            'accent' => '#84cc16',
            'cta' => 'Start Today',
            'promise' => 'A welcoming place to get stronger, fitter and healthier.',
            'services' => [
                ['Memberships', 'Flexible access to train on your schedule.'],
                ['Classes', 'Group sessions that keep you motivated.'],
                ['Personal Training', 'One-to-one coaching towards your goals.'],
            ],
        ],
    ];

    /**
     * Write the website content for the given lead.
     */
    public function write(Lead $lead, ?SiteTemplate $template = null): SiteDraft
    {
        $preset = $this->presetFor($lead->category);
        $category = $lead->category ?: 'Local Business';
        $city = $lead->city;

        $services = $preset === null
            ? [[
                'title' => Str::title($category),
                'description' => "Professional {$this->lower($category)} services delivered with care.",
            ]]
            : array_map(fn (array $service) => ['title' => $service[0], 'description' => $service[1]], $preset['services']);

        $chosenTemplate = $template ?? $lead->vibe?->template() ?? $preset['template'] ?? SiteTemplate::ModernClean;
        $accent = $preset['accent'] ?? null;

        return new SiteDraft(
            template: $chosenTemplate,
            content: [
                'accent' => in_array($accent, $chosenTemplate->palette(), true) ? $accent : $chosenTemplate->defaultAccent(),
                'headline' => $city
                    ? "Trusted {$this->headlineNoun($category, $preset)} in {$city}"
                    : "Trusted {$this->headlineNoun($category, $preset)} Near You",
                'tagline' => $preset['promise'] ?? "Quality {$this->lower($category)} from people who care.",
                'cta_label' => $preset['cta'] ?? 'Contact Us',
                'about' => $this->about($lead, $category, $preset['noun'] ?? $this->lower($category)),
                'sections' => SiteSection::defaultOrder(),
                'reviews_style' => ReviewsStyle::Carousel->value,
                'pages' => SitePages::OPTIONAL,
                'services' => $this->withSlugs($services),
                'faqs' => $this->faqs($lead),
                'custom_pages' => SiteCustomPages::seedForLead($lead),
            ],
        );
    }

    /**
     * Apply quoted headline, tagline or button changes from the instruction.
     */
    public function revise(Lead $lead, SiteDraft $current, string $instruction): SiteDraft
    {
        $content = $current->content;

        foreach (['headline' => SiteContentSchema::HEADLINE_MAX, 'tagline' => 200, 'cta_label' => 40] as $field => $max) {
            if (preg_match('/'.$field.'(?:\s+to|\s*:)\s*[\'\"](.+?)[\'\"]/i', $instruction, $match) === 1) {
                $content[$field] = Str::limit(trim($match[1]), $max, '');
            }
        }

        if (preg_match('/button(?:\s+to|\s*:)\s*[\'\"](.+?)[\'\"]/i', $instruction, $match) === 1) {
            $content['cta_label'] = Str::limit(trim($match[1]), 40, '');
        }

        return new SiteDraft($current->template, $content);
    }

    /**
     * Give each service a unique URL slug for its own page.
     *
     * @param  list<array{title: string, description: string}>  $services
     * @return list<array{title: string, description: string, slug: string}>
     */
    protected function withSlugs(array $services): array
    {
        $taken = [];
        $result = [];

        foreach ($services as $service) {
            $slug = SitePages::uniqueSlug(null, $service['title'], $taken);
            $taken[] = $slug;
            $result[] = [...$service, 'slug' => $slug];
        }

        return $result;
    }

    /**
     * Find the industry preset matching the category.
     *
     * @return array{keywords: list<string>, noun: string, template: SiteTemplate, accent: string, cta: string, promise: string, services: list<array{0: string, 1: string}>}|null
     */
    protected function presetFor(?string $category): ?array
    {
        $haystack = ' '.Str::lower((string) $category).' ';

        foreach (self::PRESETS as $preset) {
            foreach ($preset['keywords'] as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $preset;
                }
            }
        }

        return null;
    }

    /**
     * Get the noun used in the headline.
     *
     * @param  array{noun: string}|null  $preset
     */
    protected function headlineNoun(string $category, ?array $preset): string
    {
        return Str::title($preset['noun'] ?? $category);
    }

    /**
     * Write the about paragraph using only known facts.
     */
    protected function about(Lead $lead, string $category, string $noun): string
    {
        $location = $lead->city ? " in {$lead->city}" : '';

        $about = "{$lead->name} provides {$noun}{$location}. We focus on doing the job properly, communicating clearly and treating every customer with respect.";

        if ($lead->rating !== null && $lead->reviews_count > 0) {
            $about .= " Customers rate us {$this->rating($lead->rating)} out of 5 across {$lead->reviews_count} Google reviews.";
        }

        return $about;
    }

    /**
     * Build FAQs that answer from the lead's real details only.
     *
     * @return list<array{question: string, answer: string}>
     */
    protected function faqs(Lead $lead): array
    {
        $faqs = [];

        if ($lead->address) {
            $faqs[] = ['question' => 'Where are you located?', 'answer' => "You can find us at {$lead->address}."];
        }

        if ($lead->opening_hours) {
            $faqs[] = ['question' => 'What are your opening hours?', 'answer' => 'Our current hours are listed in the opening hours section of this page.'];
        }

        if ($lead->phone) {
            $faqs[] = ['question' => 'How can I get in touch?', 'answer' => "Call us on {$lead->phone} and we will be happy to help."];
        }

        return $faqs;
    }

    /**
     * Format a rating without unnecessary decimals.
     */
    protected function rating(float $rating): string
    {
        return rtrim(rtrim(number_format($rating, 1), '0'), '.');
    }

    /**
     * Lowercase a phrase safely for multibyte text.
     */
    protected function lower(string $value): string
    {
        return Str::lower($value);
    }
}

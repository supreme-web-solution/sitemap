<?php

namespace App\Services\SiteBuilder;

use App\Models\Lead;
use Illuminate\Support\Str;

/**
 * Starter landing and location pages for local SEO.
 */
class SiteCustomPages
{
    /**
     * @return list<array<string, string>>
     */
    public static function seedForLead(Lead $lead): array
    {
        $pages = [];
        $category = $lead->category ?: 'local services';
        $city = $lead->city;

        if (filled($city)) {
            $slug = Str::slug($city) ?: 'local';
            $pages[] = [
                'kind' => 'location',
                'slug' => $slug,
                'title' => "{$lead->name} in {$city}",
                'headline' => "{$category} in {$city}",
                'intro' => "Serving {$city} and nearby neighbourhoods.",
                'body' => "{$lead->name} helps customers in {$city} with dependable {$category}. Call or message us to talk through what you need.",
                'city' => $city,
                'cta_label' => 'Call now',
            ];
        }

        $pages[] = [
            'kind' => 'landing',
            'slug' => 'get-started',
            'title' => 'Get started',
            'headline' => "Work with {$lead->name}",
            'intro' => $lead->city ? "Local {$category} for {$lead->city}." : "Local {$category} you can trust.",
            'body' => "Tell us what you need and we will follow up with clear next steps. {$lead->name} keeps things straightforward from the first conversation.",
            'cta_label' => 'Contact us',
        ];

        return $pages;
    }

    /**
     * Blank page for the visual editor "add page" actions.
     *
     * @return array<string, string>
     */
    public static function template(string $kind, string $businessName, ?string $city = null, ?string $category = null): array
    {
        $category ??= 'local services';
        $suffix = (string) time();

        if ($kind === 'location') {
            $place = filled($city) ? $city : 'your area';
            $slug = Str::slug($place) ?: 'service-area';

            return [
                'kind' => 'location',
                'slug' => $slug,
                'title' => "{$businessName} in {$place}",
                'headline' => "{$category} in {$place}",
                'intro' => "Serving {$place} and nearby neighbourhoods.",
                'body' => "{$businessName} helps customers in {$place} with dependable {$category}. Call or message us to talk through what you need.",
                'city' => $city ?? '',
                'cta_label' => 'Call now',
            ];
        }

        return [
            'kind' => 'landing',
            'slug' => 'campaign-'.$suffix,
            'title' => 'New landing page',
            'headline' => "Work with {$businessName}",
            'intro' => $city ? "Local {$category} for {$city}." : "Local {$category} you can trust.",
            'body' => "Tell us what you need and we will follow up with clear next steps. {$businessName} keeps things straightforward from the first conversation.",
            'cta_label' => 'Contact us',
        ];
    }
}

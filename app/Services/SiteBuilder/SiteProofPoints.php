<?php

namespace App\Services\SiteBuilder;

use App\Models\Lead;

/**
 * Default home-page proof points that never invent credentials.
 *
 * Process steps are a generic four-step relationship. Why-choose-us bullets
 * and service-area chips are built only from facts already on the lead.
 */
class SiteProofPoints
{
    /**
     * @param  array<string, mixed>  $content
     * @return list<array{title: string, description: string}>
     */
    public static function process(Lead $lead, array $content): array
    {
        $stored = self::pairs($content['process'] ?? null);

        if ($stored !== []) {
            return $stored;
        }

        return [
            ['title' => 'Get in touch', 'description' => 'Call or write and tell us what you need.'],
            ['title' => 'We listen and plan', 'description' => 'We review the job, the timing, and the next step together.'],
            ['title' => 'We do the work', 'description' => 'The job is handled carefully, and you hear from us along the way.'],
            ['title' => 'We follow through', 'description' => 'We check the result and stay available if you need us again.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<array{title: string, description: string}>
     */
    public static function why(Lead $lead, array $content): array
    {
        $stored = self::pairs($content['why'] ?? null);

        if ($stored !== []) {
            return $stored;
        }

        $points = [];

        if (filled($lead->city)) {
            $place = $lead->state ? $lead->city.', '.$lead->state : $lead->city;
            $points[] = [
                'title' => 'Local in '.$lead->city,
                'description' => 'Based in '.$place.', so you are dealing with a nearby team.',
            ];
        }

        if ($lead->rating && $lead->reviews_count > 0) {
            $points[] = [
                'title' => number_format($lead->rating, 1).' on Google',
                'description' => number_format($lead->reviews_count).' people have left a Google review.',
            ];
        }

        if (filled($lead->phone)) {
            $points[] = [
                'title' => 'Talk to us directly',
                'description' => 'Call '.$lead->phone.' and speak with the people who do the work.',
            ];
        }

        $points[] = [
            'title' => 'A clear next step',
            'description' => 'Reach out, tell us what you need, and we will take it from there.',
        ];

        return $points;
    }

    /**
     * @return list<string>
     */
    public static function areas(Lead $lead): array
    {
        return array_values(array_unique(array_filter([
            $lead->city,
            $lead->neighborhood,
            $lead->located_in,
            $lead->state,
        ], fn (?string $value) => filled($value))));
    }

    /**
     * @return list<array{title: string, description: string}>
     */
    protected static function pairs(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $pairs = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));
            $description = trim((string) ($item['description'] ?? ''));

            if ($title === '' || $description === '') {
                continue;
            }

            $pairs[] = ['title' => $title, 'description' => $description];
        }

        return $pairs;
    }
}

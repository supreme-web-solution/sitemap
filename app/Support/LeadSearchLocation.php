<?php

namespace App\Support;

/**
 * Lead searches only resolve a city. A pasted street address is rewritten to
 * the city inside it, or refused with example cities when no city is visible.
 */
class LeadSearchLocation
{
    /** @var array<string, string> */
    private const STATES = [
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'DC' => 'District of Columbia',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
        'MD' => 'Maryland',
        'MA' => 'Massachusetts',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MS' => 'Mississippi',
        'MO' => 'Missouri',
        'MT' => 'Montana',
        'NE' => 'Nebraska',
        'NV' => 'Nevada',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NY' => 'New York',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
        'ON' => 'Ontario',
        'BC' => 'British Columbia',
        'QC' => 'Quebec',
        'AB' => 'Alberta',
    ];

    /**
     * @return array{ok: bool, location: string, notice: string|null}
     */
    public static function forSearch(string $location): array
    {
        $location = trim($location);

        if (! self::looksLikeStreet($location)) {
            return ['ok' => true, 'location' => $location, 'notice' => null];
        }

        $city = self::cityFromAddress($location);
        $examples = self::examples();

        if ($city !== null) {
            return [
                'ok' => true,
                'location' => $city,
                'notice' => "A street address will not run, so this uses {$city}, the city in that address. Enter a city next time, such as {$examples}.",
            ];
        }

        return [
            'ok' => false,
            'location' => $location,
            'notice' => "That location is a street address, and a search only accepts a city. Enter a city such as {$examples}, or the city named in the address.",
        ];
    }

    public static function looksLikeStreet(string $location): bool
    {
        $value = trim($location);

        if ($value === '') {
            return false;
        }

        if (preg_match('/^\d/', $value) === 1 || str_contains($value, '#')) {
            return true;
        }

        return preg_match('/\b(blvd|boulevard|street|avenue|suite|road|drive|lane|highway|parkway|court)\b/i', $value) === 1;
    }

    public static function cityFromAddress(string $location): ?string
    {
        $cleaned = trim((string) preg_replace('/\b\d{5}(?:-\d{4})?\b/', '', $location));
        $parts = array_values(array_filter(array_map(trim(...), explode(',', $cleaned)), fn (string $part) => $part !== ''));

        for ($index = count($parts) - 1; $index >= 1; $index--) {
            $state = self::stateName($parts[$index]);

            if ($state === null) {
                continue;
            }

            $city = $parts[$index - 1];

            if ($city === '' || self::looksLikeStreet($city)) {
                continue;
            }

            return $city.', '.$state;
        }

        return null;
    }

    public static function examples(): string
    {
        $preferred = ['Dallas, Texas', 'Austin, Texas', 'Miami, Florida', 'Charlotte, North Carolina'];
        $available = config('lead-search.locations', []);
        $picked = array_values(array_filter(
            $preferred,
            fn (string $city) => in_array($city, $available, true),
        ));

        if (count($picked) < 3) {
            $picked = array_slice($available, 0, 4);
        }

        if ($picked === []) {
            return 'Dallas, Texas';
        }

        if (count($picked) === 1) {
            return $picked[0];
        }

        $last = array_pop($picked);

        return implode(', ', $picked).', or '.$last;
    }

    private static function stateName(string $part): ?string
    {
        $token = strtoupper(trim((string) preg_replace('/\s+/', ' ', $part)));
        $token = trim((string) preg_replace('/\b\d{5}(?:-\d{4})?\b/', '', $token));

        if (isset(self::STATES[$token])) {
            return self::STATES[$token];
        }

        foreach (self::STATES as $name) {
            if (strcasecmp($name, $token) === 0) {
                return $name;
            }
        }

        return null;
    }
}

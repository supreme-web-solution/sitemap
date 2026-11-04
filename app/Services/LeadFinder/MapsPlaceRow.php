<?php

namespace App\Services\LeadFinder;

use App\Data\BusinessListing;
use Illuminate\Support\Str;

/**
 * Normalizes Google Maps place rows from Mindcase (camelCase API or export-style keys).
 */
readonly class MapsPlaceRow
{
    /**
     * @param  array<string, string>|null  $openingHours
     * @param  list<string>  $additionalPhones
     * @param  array<string, string>  $socialLinks
     * @param  list<string>  $services
     * @param  list<string>  $photoUrls
     */
    public function __construct(
        public string $placeId,
        public string $name,
        public ?string $category = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $website = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $country = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?float $rating = null,
        public int $reviewsCount = 0,
        public ?array $openingHours = null,
        public ?string $description = null,
        public ?string $photoUrl = null,
        public ?string $mapsUrl = null,
        public array $additionalPhones = [],
        public array $socialLinks = [],
        public ?string $neighborhood = null,
        public ?string $locatedIn = null,
        public array $services = [],
        public array $photoUrls = [],
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function from(array $row): ?self
    {
        $placeId = self::string($row, 'placeId', 'Place ID', 'place_id');
        $name = self::string($row, 'businessName', 'Business Name', 'name');

        if ($placeId === null || $name === null) {
            return null;
        }

        if (self::bool($row, 'permanentlyClosed', 'Permanently Closed')) {
            return null;
        }

        $latitude = self::float($row, 'latitude', 'Latitude');
        $longitude = self::float($row, 'longitude', 'Longitude');
        $category = self::string($row, 'category', 'Category');
        $coverPhoto = self::string($row, 'coverPhoto', 'Cover Photo', 'photo');
        $photoUrls = self::photoUrls($row, $coverPhoto);

        return new self(
            placeId: $placeId,
            name: $name,
            category: $category,
            phone: self::string($row, 'phone', 'Phone'),
            email: self::firstEmail(self::value($row, 'emails', 'Emails', 'email', 'Email')),
            website: self::string($row, 'website', 'Website'),
            address: self::string($row, 'address', 'Address'),
            city: self::string($row, 'city', 'City'),
            state: self::string($row, 'state', 'State'),
            country: self::string($row, 'country', 'Country'),
            latitude: $latitude,
            longitude: $longitude,
            rating: self::float($row, 'rating', 'Rating'),
            reviewsCount: (int) (self::value($row, 'reviews', 'Reviews') ?? 0),
            openingHours: self::openingHours(self::value($row, 'openingHours', 'Opening Hours')),
            description: self::description($row),
            photoUrl: $coverPhoto ?? ($photoUrls[0] ?? null),
            mapsUrl: self::string($row, 'placeUrl', 'Place URL', 'maps_url'),
            additionalPhones: self::phoneList(self::value($row, 'additionalPhones', 'Additional Phones')),
            socialLinks: self::socialLinks(self::value($row, 'socials', 'Socials')),
            neighborhood: self::string($row, 'neighborhood', 'Neighborhood'),
            locatedIn: self::string($row, 'locatedIn', 'Located In'),
            services: self::services($row, $category),
            photoUrls: $photoUrls,
        );
    }

    public function toListing(): BusinessListing
    {
        return new BusinessListing(
            placeId: $this->placeId,
            name: $this->name,
            category: $this->category,
            phone: $this->phone,
            email: $this->email,
            website: $this->website,
            address: $this->address,
            city: $this->city,
            state: $this->state,
            country: $this->country,
            latitude: $this->latitude,
            longitude: $this->longitude,
            rating: $this->rating,
            reviewsCount: $this->reviewsCount,
            openingHours: $this->openingHours,
            description: $this->description,
            photoUrl: $this->photoUrl,
            mapsUrl: $this->mapsUrl,
            additionalPhones: $this->additionalPhones,
            socialLinks: $this->socialLinks,
            neighborhood: $this->neighborhood,
            locatedIn: $this->locatedIn,
            services: $this->services,
            photoUrls: $this->photoUrls,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  string  ...$keys
     */
    protected static function value(array $row, ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  string  ...$keys
     */
    protected static function string(array $row, ...$keys): ?string
    {
        $value = self::value($row, ...$keys);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  string  ...$keys
     */
    protected static function float(array $row, ...$keys): ?float
    {
        $value = self::value($row, ...$keys);

        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  string  ...$keys
     */
    protected static function bool(array $row, ...$keys): bool
    {
        $value = self::value($row, ...$keys);

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    protected static function firstEmail(mixed $value): ?string
    {
        foreach (self::stringList($value) as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected static function phoneList(mixed $value): array
    {
        $phones = [];

        foreach (self::stringList($value) as $candidate) {
            $normalized = preg_replace('/[^\d+]/', '', $candidate) ?? '';

            if ($normalized !== '') {
                $phones[] = $candidate;
            }
        }

        return array_values(array_unique($phones));
    }

    /**
     * @return array<string, string>
     */
    protected static function socialLinks(mixed $value): array
    {
        $decoded = self::decodeJson($value);

        if (! is_array($decoded)) {
            return [];
        }

        $links = [];

        foreach ($decoded as $platform => $urls) {
            if (! is_string($platform)) {
                continue;
            }

            $url = self::firstUrl($urls);

            if ($url !== null) {
                $links[strtolower($platform)] = $url;
            }
        }

        return $links;
    }

    /**
     * @return array<string, string>|null
     */
    protected static function openingHours(mixed $value): ?array
    {
        $decoded = self::decodeJson($value);

        if (! is_array($decoded)) {
            return null;
        }

        $normalized = array_filter(
            $decoded,
            fn ($hours, $day) => is_string($day) && is_string($hours),
            ARRAY_FILTER_USE_BOTH,
        );

        return $normalized === [] ? null : $normalized;
    }

    protected static function decodeJson(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * @return list<string>
     */
    protected static function stringList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return collect($value)
                ->flatMap(fn ($item) => self::stringList($item))
                ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                ->values()
                ->all();
        }

        if (is_string($value)) {
            $decoded = self::decodeJson($value);

            if (is_array($decoded)) {
                return self::stringList($decoded);
            }

            $parts = preg_split('/[;,|]/', $value) ?: [];

            return collect($parts)
                ->map(fn ($part) => trim($part))
                ->filter()
                ->values()
                ->all();
        }

        return [];
    }

    protected static function firstUrl(mixed $urls): ?string
    {
        foreach (self::stringList($urls) as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected static function description(array $row): ?string
    {
        $direct = self::string($row, 'description', 'Description');

        if ($direct !== null) {
            return $direct;
        }

        $posts = self::decodeJson(self::value($row, 'ownerPosts', 'Owner Posts'));

        if (! is_array($posts)) {
            return null;
        }

        foreach ($posts as $post) {
            if (! is_array($post) || ! isset($post['text']) || ! is_string($post['text'])) {
                continue;
            }

            $text = trim(preg_replace('/\s+/u', ' ', strip_tags($post['text'])) ?? '');

            if (mb_strlen($text) < 60) {
                continue;
            }

            return Str::limit($text, 800);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    protected static function services(array $row, ?string $category): array
    {
        $services = [];
        $attributes = self::decodeJson(self::value($row, 'attributes', 'Attributes'));

        if (is_array($attributes) && isset($attributes['Offerings']) && is_array($attributes['Offerings'])) {
            foreach ($attributes['Offerings'] as $offering) {
                if (is_string($offering) && trim($offering) !== '') {
                    $services[] = trim($offering);
                }
            }
        }

        $allCategories = self::string($row, 'allCategories', 'All Categories');

        if ($allCategories !== null) {
            foreach (preg_split('/\s*;\s*/', $allCategories) ?: [] as $part) {
                $part = trim($part);

                if ($part === '' || ($category !== null && strcasecmp($part, $category) === 0)) {
                    continue;
                }

                $services[] = $part;
            }
        }

        return self::uniqueStrings($services, 5);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    protected static function photoUrls(array $row, ?string $coverPhoto): array
    {
        $urls = [];

        if ($coverPhoto !== null) {
            $urls[] = $coverPhoto;
        }

        $decoded = self::decodeJson(self::value($row, 'photos', 'Photos'));

        if (is_array($decoded)) {
            foreach ($decoded as $photo) {
                if (is_array($photo) && isset($photo['url']) && is_string($photo['url'])) {
                    $urls[] = trim($photo['url']);
                }
            }
        }

        $filtered = [];

        foreach ($urls as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $filtered[] = $url;
            }
        }

        return self::uniqueStrings($filtered, 8);
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    protected static function uniqueStrings(array $items, int $max): array
    {
        $seen = [];
        $unique = [];

        foreach ($items as $item) {
            $key = Str::lower(trim($item));

            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = trim($item);

            if (count($unique) >= $max) {
                break;
            }
        }

        return $unique;
    }
}

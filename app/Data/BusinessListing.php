<?php

namespace App\Data;

readonly class BusinessListing
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
    ) {
        //
    }

    public function withEmail(?string $email): self
    {
        return new self(
            placeId: $this->placeId,
            name: $this->name,
            category: $this->category,
            phone: $this->phone,
            email: $email,
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
}

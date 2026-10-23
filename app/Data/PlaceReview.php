<?php

namespace App\Data;

readonly class PlaceReview
{
    public function __construct(
        public string $externalId,
        public string $authorName,
        public int $rating,
        public string $body,
        public ?string $postedAt = null,
    ) {
        //
    }
}

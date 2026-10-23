<?php

namespace App\Data;

use App\Enums\LeadSearchStatus;

readonly class ReviewFinderResult
{
    /**
     * @param  list<PlaceReview>  $reviews
     */
    public function __construct(
        public LeadSearchStatus $status,
        public ?string $providerJobId = null,
        public array $reviews = [],
        public ?string $error = null,
    ) {
        //
    }
}

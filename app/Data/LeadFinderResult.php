<?php

namespace App\Data;

use App\Enums\LeadSearchStatus;

readonly class LeadFinderResult
{
    /**
     * @param  list<BusinessListing>  $listings
     */
    public function __construct(
        public LeadSearchStatus $status,
        public ?string $providerJobId = null,
        public array $listings = [],
        public ?string $error = null,
    ) {
        //
    }
}

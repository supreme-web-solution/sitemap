<?php

namespace App\Data;

use App\Enums\LeadSearchStatus;

readonly class LeadFinderResult
{
    /**
     * @param  list<BusinessListing>  $listings
     * @param  string|null  $error  Safe text for the product UI.
     * @param  string|null  $providerDetail  Raw provider wording for the admin credit log.
     */
    public function __construct(
        public LeadSearchStatus $status,
        public ?string $providerJobId = null,
        public array $listings = [],
        public ?string $error = null,
        public ?string $providerDetail = null,
    ) {
        //
    }
}

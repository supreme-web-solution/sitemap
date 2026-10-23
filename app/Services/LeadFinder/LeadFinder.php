<?php

namespace App\Services\LeadFinder;

use App\Data\LeadFinderResult;
use App\Models\LeadSearch;

interface LeadFinder
{
    /**
     * Start a search with the provider, returning rows if it finishes quickly.
     */
    public function start(LeadSearch $search): LeadFinderResult;

    /**
     * Check on a search that the provider is still running.
     */
    public function poll(string $providerJobId): LeadFinderResult;
}

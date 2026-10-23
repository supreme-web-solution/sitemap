<?php

namespace App\Services\ReviewFinder;

use App\Data\ReviewFinderResult;
use App\Models\Lead;

interface ReviewFinder
{
    /**
     * Start collecting a business's reviews, returning them if the provider finishes quickly.
     */
    public function start(Lead $lead): ReviewFinderResult;

    /**
     * Check on a collection run that the provider is still working on.
     */
    public function poll(string $providerJobId): ReviewFinderResult;
}

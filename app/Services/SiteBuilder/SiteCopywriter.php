<?php

namespace App\Services\SiteBuilder;

use App\Data\SiteDraft;
use App\Enums\SiteTemplate;
use App\Models\Lead;

interface SiteCopywriter
{
    /**
     * Write the website content for the given lead.
     *
     * Implementations must only state facts present on the lead and must
     * never invent awards, prices, years in business or certifications.
     */
    public function write(Lead $lead, ?SiteTemplate $template = null): SiteDraft;

    /**
     * Apply a plain-English edit to an existing website plan.
     */
    public function revise(Lead $lead, SiteDraft $current, string $instruction): SiteDraft;
}

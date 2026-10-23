<?php

namespace App\Services\SiteBuilder;

use App\Data\SiteDraft;
use App\Models\Site;

class SiteEditor
{
    public function __construct(protected SiteCopywriter $copywriter) {}

    /**
     * Return an updated plan for the site from a plain-English instruction.
     */
    public function apply(Site $site, string $instruction): SiteDraft
    {
        $site->loadMissing('lead');

        return $this->copywriter->revise(
            $site->lead,
            new SiteDraft($site->template, $site->content),
            $instruction,
        );
    }
}

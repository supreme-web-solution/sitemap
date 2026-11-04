<?php

namespace App\Actions\Sites;

use App\Data\SiteDraft;
use App\Models\Lead;
use App\Models\Site;
use App\Models\SiteConcept;

class ChooseSiteConcept
{
    public function __construct(protected GenerateSite $generateSite) {}

    /**
     * Turn the chosen concept into the lead's website, keeping the previous version if one exists.
     */
    public function handle(Lead $lead, SiteConcept $concept, ?int $userId = null): Site
    {
        if ($lead->site) {
            $lead->site->snapshot($userId, __('Before choosing a concept'));
        }

        $site = $this->generateSite->handle(
            $lead,
            $concept->template,
            new SiteDraft($concept->template, $concept->content),
            $concept->design_template_id,
        );

        $lead->forceFill(['vibe' => $concept->template->vibe()])->save();

        return $site;
    }
}

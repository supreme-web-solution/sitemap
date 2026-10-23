<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteConcept;
use App\Services\SiteBuilder\SiteRenderer;
use Illuminate\Http\Response;

class ConceptPreviewController extends Controller
{
    /**
     * Show a throwaway preview of one of the three design options.
     */
    public function __invoke(SiteConcept $siteConcept, SiteRenderer $renderer): Response
    {
        $html = $renderer->render($this->asSite($siteConcept), asPreview: true);

        abort_if($html === null, 404);

        return response($html)->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Build an unsaved site the renderer can draw from the concept's copy and template.
     */
    protected function asSite(SiteConcept $concept): Site
    {
        $lead = $concept->lead()->with('team')->firstOrFail();

        $site = new Site;
        $site->forceFill([
            'team_id' => $concept->team_id,
            'lead_id' => $concept->lead_id,
            'slug' => $lead->site?->slug ?? 'concept-'.$concept->id,
            'template' => $concept->template,
            'content' => $concept->content,
        ]);
        $site->setRelation('lead', $lead);
        $site->setRelation('team', $lead->team);

        return $site;
    }
}

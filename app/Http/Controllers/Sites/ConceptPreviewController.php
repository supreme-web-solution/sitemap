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
        $html = $renderer->render($this->asSite($siteConcept), asPreview: true, blockIndexing: true);

        abort_if($html === null, 404);

        return response($html)->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Build an unsaved site the renderer can draw from the concept's copy and template.
     *
     * Reviews are omitted so theme comparison stays fast and focused on layout.
     */
    protected function asSite(SiteConcept $concept): Site
    {
        $lead = $concept->lead()->with('team')->firstOrFail();
        $lead->setRelation('reviews', $lead->reviews()->getModel()->newCollection());

        $content = $concept->content;
        $sections = is_array($content['sections'] ?? null) ? $content['sections'] : [];
        $content['sections'] = array_values(array_filter(
            $sections,
            fn (mixed $section): bool => $section !== 'reviews',
        ));

        $site = new Site;
        $site->forceFill([
            'team_id' => $concept->team_id,
            'lead_id' => $concept->lead_id,
            'slug' => $lead->site?->slug ?? 'concept-'.$concept->id,
            'template' => $concept->template,
            'content' => $content,
            'design_template_id' => $concept->design_template_id,
        ]);
        $site->setRelation('designTemplate', $concept->designTemplate);
        $site->setRelation('lead', $lead);
        $site->setRelation('team', $lead->team);

        return $site;
    }
}

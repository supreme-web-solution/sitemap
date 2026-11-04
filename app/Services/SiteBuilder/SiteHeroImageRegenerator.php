<?php

namespace App\Services\SiteBuilder;

use App\Http\Requests\Leads\UpdateLeadProfileRequest;
use App\Models\Site;
use Illuminate\Support\Facades\Storage;

class SiteHeroImageRegenerator
{
    public function __construct(
        protected LeadPhotoGenerator $images,
        protected SiteImageGenerationPlanner $planner,
    ) {}

    /**
     * Generate a new hero photo (lead photo index 0) and attach it to the lead.
     *
     * @param  list<string>  $attachmentPaths
     *
     * @throws \RuntimeException
     */
    public function regenerate(Site $site, ?string $userHint = null, array $attachmentPaths = []): string
    {
        $site->loadMissing('lead');
        $lead = $site->lead;

        if ($lead->team_id === null) {
            throw new \RuntimeException(__('This lead is missing a team.'));
        }

        $plan = $this->planner->plan($site, $lead, trim((string) $userHint), 0, $attachmentPaths);

        SiteEditLog::info('hero image generation', [
            'site_id' => $site->id,
            'lead_id' => $lead->id,
            'placement' => $plan->placement,
            'aspect_ratio' => $plan->aspectRatio,
            'from_llm' => $plan->fromLlm,
            'prompt' => $plan->prompt,
        ]);

        $generated = $this->images->generate($plan, (int) $lead->team_id, (int) $lead->id);

        $paths = array_values($lead->photo_paths ?? []);

        if (isset($paths[0])) {
            Storage::disk('public')->delete($paths[0]);
            $paths[0] = $generated['path'];
        } elseif (count($paths) >= UpdateLeadProfileRequest::MAX_PHOTOS) {
            Storage::disk('public')->delete($generated['path']);

            throw new \RuntimeException(__('A website can have up to :max photos.', ['max' => UpdateLeadProfileRequest::MAX_PHOTOS]));
        } else {
            array_unshift($paths, $generated['path']);
        }

        $lead->forceFill(['photo_paths' => array_values($paths)])->save();

        return __('Generated a new hero photo and attached it to this site.');
    }
}

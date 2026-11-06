<?php

namespace App\Actions\Leads;

use App\Models\Lead;
use App\Services\Media\SiteImageStorage;

class DeleteLead
{
    public function __construct(protected SiteImageStorage $images) {}

    /**
     * Remove the lead and everything stored for it, including the website.
     */
    public function handle(Lead $lead): void
    {
        $lead->loadMissing('site');

        foreach ($this->storedPaths($lead) as $path) {
            $this->images->delete($path);
        }

        $lead->site?->delete();
        $lead->delete();
    }

    /**
     * @return list<string>
     */
    protected function storedPaths(Lead $lead): array
    {
        $paths = [];

        if (is_string($lead->logo_path) && $lead->logo_path !== '') {
            $paths[] = $lead->logo_path;
        }

        foreach ($lead->photo_paths ?? [] as $path) {
            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        $content = $lead->site?->content ?? [];

        foreach (is_array($content['images'] ?? null) ? $content['images'] : [] as $path) {
            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        foreach (is_array($content['services'] ?? null) ? $content['services'] : [] as $service) {
            $path = is_array($service) ? ($service['image_path'] ?? null) : null;

            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }
}

<?php

namespace App\Actions\Sites;

use App\Data\SiteDraft;
use App\Enums\LeadStatus;
use App\Enums\SiteGenerationStatus;
use App\Enums\SiteTemplate;
use App\Models\Lead;
use App\Models\Site;
use App\Services\SiteBuilder\SiteCopywriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateSite
{
    public function __construct(protected SiteCopywriter $copywriter)
    {
        //
    }

    /**
     * Generate (or regenerate) the website for the given lead.
     */
    public function handle(Lead $lead, ?SiteTemplate $template = null, ?SiteDraft $draft = null): Site
    {
        $draft ??= $this->copywriter->write($lead, $template);

        return DB::transaction(function () use ($lead, $draft) {
            $site = $lead->site ?? new Site([
                'lead_id' => $lead->id,
                'slug' => $this->slugFor($lead),
            ]);

            $site->team_id = $lead->team_id;
            $site->fill([
                'template' => $draft->template,
                'content' => $draft->content,
            ])->save();

            $lead->fill([
                'site_generation_status' => SiteGenerationStatus::Idle,
                'site_generation_error' => null,
            ]);

            if ($lead->status === LeadStatus::New) {
                $lead->status = LeadStatus::SiteGenerated;
            }

            $lead->save();

            return $site;
        });
    }

    /**
     * Build a readable, hard-to-guess public slug for the lead's preview.
     */
    protected function slugFor(Lead $lead): string
    {
        $base = Str::limit(Str::slug($lead->name), 40, '') ?: 'site';

        return rtrim($base, '-').'-'.Str::lower(Str::random(6));
    }
}

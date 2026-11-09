<?php

namespace App\Actions\Sites;

use App\Data\SiteDraft;
use App\Enums\LeadStatus;
use App\Enums\SiteGenerationStatus;
use App\Enums\SiteTemplate;
use App\Models\Lead;
use App\Models\Site;
use App\Models\SiteDesignTemplate;
use App\Services\SiteBuilder\SectionVariants;
use App\Services\SiteBuilder\SiteBrandAssets;
use App\Services\SiteBuilder\SiteBuildProgress;
use App\Services\SiteBuilder\SiteContentSchema;
use App\Services\SiteBuilder\SiteCopywriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateSite
{
    public function __construct(
        protected SiteCopywriter $copywriter,
        protected SiteBrandAssets $brandAssets,
    ) {}

    /**
     * Generate (or regenerate) the website for the given lead.
     */
    public function handle(Lead $lead, ?SiteTemplate $template = null, ?SiteDraft $draft = null, ?int $designTemplateId = null): Site
    {
        $designTemplate = $designTemplateId ? SiteDesignTemplate::query()->find($designTemplateId) : null;
        $template ??= $designTemplate?->siteTemplate();

        if ($draft === null) {
            SiteBuildProgress::record($lead->id, 'writing');

            $draft = $this->copywriter->write($lead, $template);
        }

        if ($designTemplate !== null) {
            $content = $draft->content;
            $palette = $draft->template->palette();

            if (in_array($designTemplate->accent_color, $palette, true)) {
                $content['accent'] = $designTemplate->accent_color;
            }

            $content['variants'] = SectionVariants::withDefaults($designTemplate->variants);

            $draft = new SiteDraft($designTemplate->siteTemplate(), $content);
        }

        SiteBuildProgress::record($lead->id, 'saving');

        return DB::transaction(function () use ($lead, $draft, $designTemplate) {
            $previous = $lead->site?->content ?? [];
            $site = $lead->site ?? new Site([
                'lead_id' => $lead->id,
                'slug' => $this->slugFor($lead),
            ]);

            $site->team_id = $lead->team_id;
            $site->fill([
                'template' => $draft->template,
                'design_template_id' => $designTemplate?->id,
                'content' => SiteContentSchema::preserveEditorFields($previous, $draft->content),
            ])->save();

            $lead->fill([
                'site_generation_status' => SiteGenerationStatus::Idle,
                'site_generation_error' => null,
            ]);

            if ($lead->status === LeadStatus::New) {
                $lead->status = LeadStatus::SiteGenerated;
            }

            $lead->save();

            SiteBuildProgress::record($lead->id, 'assets');

            return $this->brandAssets->refresh($site->fresh(['lead']));
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

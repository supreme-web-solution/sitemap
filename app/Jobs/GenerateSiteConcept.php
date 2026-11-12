<?php

namespace App\Jobs;

use App\Enums\SiteVibe;
use App\Models\Lead;
use App\Models\SiteConcept;
use App\Models\SiteDesignTemplate;
use App\Services\SiteBuilder\RuleBasedCopywriter;
use App\Services\SiteBuilder\SectionVariants;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateSiteConcept implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * Concepts use rule-based theme drafts only, so this stays short.
     */
    public int $timeout = 20;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * Seconds to wait before retrying after an exception.
     */
    public int $backoff = 5;

    public function __construct(public Lead $lead, public string $batch, public SiteVibe $vibe, public ?int $designTemplateId = null) {}

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->lead->id.'-'.$this->batch.'-'.$this->vibe->value;
    }

    /**
     * Write one lightweight design option: theme + basic lead copy, no AI site build.
     */
    public function handle(RuleBasedCopywriter $copywriter): void
    {
        $template = $this->vibe->template();
        $draft = $copywriter->write($this->lead, $template);
        $content = $draft->content;

        $design = $this->designTemplateId
            ? SiteDesignTemplate::query()->where('base_template', $template->value)->find($this->designTemplateId)
            : null;

        if ($design !== null && in_array($design->accent_color, $template->palette(), true)) {
            $content['accent'] = $design->accent_color;
        }

        if ($design !== null) {
            $content['variants'] = SectionVariants::withDefaults($design->variants);
        }

        SiteConcept::query()->updateOrCreate(
            [
                'lead_id' => $this->lead->id,
                'batch' => $this->batch,
                'template' => $template,
            ],
            [
                'team_id' => $this->lead->team_id,
                'design_template_id' => $design?->id,
                'content' => $content,
            ],
        );
    }
}

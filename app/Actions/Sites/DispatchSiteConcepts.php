<?php

namespace App\Actions\Sites;

use App\Enums\SiteGenerationStatus;
use App\Enums\SiteVibe;
use App\Jobs\GenerateSiteConcept;
use App\Models\Lead;
use App\Models\SiteDesignTemplate;
use App\Services\SiteBuilder\EnsureDesignTemplateCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

class DispatchSiteConcepts
{
    public function __construct(protected EnsureDesignTemplateCatalog $catalog) {}

    /**
     * Build three theme options immediately.
     *
     * Concepts are lightweight (rule-based copy + catalog layouts), not full AI
     * website builds, so they run on the sync connection and finish in the request.
     */
    public function handle(Lead $lead): void
    {
        $this->catalog->ensure();

        $batch = (string) Str::ulid();
        $leadId = $lead->id;
        $recentDesignIds = $lead->concepts()->pluck('design_template_id')->filter()->all();

        $lead->update([
            'concepts_status' => SiteGenerationStatus::Queued,
            'concepts_requested_at' => now(),
        ]);

        $lead->concepts()->delete();

        Bus::batch(
            collect(SiteVibe::cases())
                ->map(fn (SiteVibe $vibe) => new GenerateSiteConcept(
                    $lead,
                    $batch,
                    $vibe,
                    $this->pickDesign($lead, $vibe, $recentDesignIds)?->id,
                ))
                ->all(),
        )
            ->then(function () use ($leadId) {
                Lead::query()->whereKey($leadId)->update(['concepts_status' => SiteGenerationStatus::Idle->value]);
            })
            ->catch(function () use ($leadId) {
                $lead = Lead::query()->find($leadId);

                if ($lead === null) {
                    return;
                }

                $lead->update([
                    'concepts_status' => $lead->concepts()->exists()
                        ? SiteGenerationStatus::Idle->value
                        : SiteGenerationStatus::Failed->value,
                ]);
            })
            ->name('site-concepts-'.$leadId)
            ->onConnection('sync')
            ->dispatch();
    }

    /**
     * Choose a catalog design for the vibe: fresh ones first, and ones fitting the lead's industry before the rest.
     *
     * @param  list<int>  $recentDesignIds
     */
    protected function pickDesign(Lead $lead, SiteVibe $vibe, array $recentDesignIds): ?SiteDesignTemplate
    {
        $candidates = SiteDesignTemplate::query()
            ->active()
            ->where('base_template', $vibe->template()->value)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $fresh = $candidates->reject(fn (SiteDesignTemplate $design) => in_array($design->id, $recentDesignIds, true));
        $pool = $fresh->isEmpty() ? $candidates : $fresh;
        $matching = $pool->filter(fn (SiteDesignTemplate $design) => $this->fitsIndustry($design, $lead));

        return ($matching->isNotEmpty() ? $matching : $pool)->random();
    }

    /**
     * Whether any industry the design lists appears in the lead's category.
     */
    protected function fitsIndustry(SiteDesignTemplate $design, Lead $lead): bool
    {
        $category = Str::lower((string) $lead->category);

        if ($category === '') {
            return false;
        }

        return Collection::make($design->industries)
            ->contains(fn (string $industry) => str_contains($category, Str::lower($industry)));
    }
}

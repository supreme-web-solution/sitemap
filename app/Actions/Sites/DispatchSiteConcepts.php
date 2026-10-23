<?php

namespace App\Actions\Sites;

use App\Enums\SiteGenerationStatus;
use App\Enums\SiteVibe;
use App\Jobs\GenerateSiteConcept;
use App\Models\Lead;
use App\Support\Queues;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

class DispatchSiteConcepts
{
    /**
     * Queue one job per design vibe so the three options can generate in parallel.
     */
    public function handle(Lead $lead): void
    {
        $batch = (string) Str::ulid();
        $leadId = $lead->id;

        $lead->update([
            'concepts_status' => SiteGenerationStatus::Queued,
            'concepts_requested_at' => now(),
        ]);

        $lead->concepts()->delete();

        Bus::batch(
            collect(SiteVibe::cases())
                ->map(fn (SiteVibe $vibe) => new GenerateSiteConcept($lead, $batch, $vibe))
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
            ->onQueue(Queues::AI)
            ->dispatch();
    }
}

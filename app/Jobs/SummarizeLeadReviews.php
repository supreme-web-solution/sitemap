<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SummarizeLeadReviews implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 40;

    public int $tries = 1;

    public function __construct(public Lead $lead)
    {
        $this->onQueue(Queues::AI);
    }

    public function uniqueId(): string
    {
        return 'reviews-'.$this->lead->id;
    }

    /**
     * Pull common praise and complaints out of stored Google reviews (no invented quotes).
     */
    public function handle(): void
    {
        $reviews = $this->lead->reviews()->whereNotNull('body')->get(['rating', 'body']);

        $praise = $reviews->where('rating', '>=', 4)->pluck('body');
        $complaints = $reviews->where('rating', '<=', 3)->pluck('body');

        $this->lead->forceFill([
            'reviews_summary' => [
                'reviewCount' => $reviews->count(),
                'averageRating' => $reviews->avg('rating') !== null ? round((float) $reviews->avg('rating'), 1) : null,
                'praise' => $this->phrases($praise),
                'complaints' => $this->phrases($complaints),
            ],
        ])->save();
    }

    /**
     * @param  Collection<int, string>  $bodies
     * @return list<string>
     */
    protected function phrases($bodies): array
    {
        $text = Str::lower($bodies->implode(' '));
        $candidates = [
            'friendly' => 'Friendly staff',
            'professional' => 'Professional service',
            'fast' => 'Fast turnaround',
            'price' => 'Pricing',
            'wait' => 'Waiting times',
            'clean' => 'Clean premises',
            'quality' => 'Quality of work',
            'late' => 'Punctuality',
            'recommend' => 'Would recommend',
        ];

        $found = [];

        foreach ($candidates as $needle => $label) {
            if (str_contains($text, $needle)) {
                $found[] = $label;
            }
        }

        return array_slice($found, 0, 5);
    }
}

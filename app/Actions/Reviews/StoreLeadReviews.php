<?php

namespace App\Actions\Reviews;

use App\Data\PlaceReview;
use App\Enums\SiteGenerationStatus;
use App\Models\Lead;
use Illuminate\Support\Str;

class StoreLeadReviews
{
    /**
     * Keep the best written reviews for the lead and mark the fetch as done.
     *
     * Reviews the owner has already hidden stay hidden when they are fetched again.
     *
     * @param  list<PlaceReview>  $reviews
     */
    public function handle(Lead $lead, array $reviews): int
    {
        $selected = $this->select($reviews);

        foreach ($selected as $review) {
            $lead->reviews()->updateOrCreate(
                ['external_id' => $review->externalId],
                [
                    'author_name' => Str::limit($review->authorName, 100, ''),
                    'rating' => $review->rating,
                    'body' => $this->tidy($review->body),
                    'posted_at_text' => $review->postedAt ? Str::limit($review->postedAt, 60, '') : null,
                ],
            );
        }

        $lead->update([
            'reviews_fetched_at' => now(),
            'reviews_fetch_status' => SiteGenerationStatus::Idle,
            'reviews_fetch_error' => null,
        ]);

        return count($selected);
    }

    /**
     * Pick the highest rated, most substantial reviews with real text.
     *
     * @param  list<PlaceReview>  $reviews
     * @return list<PlaceReview>
     */
    protected function select(array $reviews): array
    {
        $minimumRating = (int) config('prospecting.reviews.minimum_rating');
        $minimumLength = (int) config('prospecting.reviews.minimum_length');

        return collect($reviews)
            ->filter(fn (PlaceReview $review) => $review->rating >= $minimumRating
                && mb_strlen(trim($review->body)) >= $minimumLength)
            ->unique('externalId')
            ->sortBy([
                fn (PlaceReview $a, PlaceReview $b) => $b->rating <=> $a->rating,
                fn (PlaceReview $a, PlaceReview $b) => min(mb_strlen($b->body), 240) <=> min(mb_strlen($a->body), 240),
            ])
            ->take((int) config('prospecting.reviews.stored_per_lead'))
            ->values()
            ->all();
    }

    /**
     * Collapse whitespace and trim very long reviews at a word boundary.
     */
    protected function tidy(string $body): string
    {
        $body = trim((string) preg_replace('/\s+/u', ' ', $body));

        return Str::limit($body, (int) config('prospecting.reviews.maximum_length'), '…', preserveWords: true);
    }
}

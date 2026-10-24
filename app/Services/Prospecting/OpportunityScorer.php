<?php

namespace App\Services\Prospecting;

use App\Models\Lead;

class OpportunityScorer
{
    /**
     * @var list<string>
     */
    public const WEIGHT_KEYS = [
        'website_need',
        'website_quality',
        'business_activity',
        'contactability',
        'profile_fit',
    ];

    /**
     * Score how promising a lead is as a website client.
     *
     * @return array{score: int, breakdown: list<array{key: string, label: string, points: int, max: int, reason: string}>}
     */
    public function score(Lead $lead): array
    {
        $weights = $lead->team?->scoreWeights() ?? config('prospecting.score_weights');

        $components = [
            'website_need' => ['Website need', ...$this->websiteNeed($lead)],
            'website_quality' => ['Website quality', ...$this->websiteQuality($lead)],
            'business_activity' => ['Business activity', ...$this->businessActivity($lead)],
            'contactability' => ['Contact details', ...$this->contactability($lead)],
            'profile_fit' => ['Profile completeness', ...$this->profileFit($lead)],
        ];

        $breakdown = [];
        $total = 0;

        foreach ($components as $key => [$label, $ratio, $reason]) {
            $max = (int) ($weights[$key] ?? 0);
            $points = (int) round($max * max(0.0, min(1.0, $ratio)));
            $total += $points;

            $breakdown[] = [
                'key' => $key,
                'label' => $label,
                'points' => $points,
                'max' => $max,
                'reason' => $reason,
            ];
        }

        return ['score' => min(100, $total), 'breakdown' => $breakdown];
    }

    /**
     * Get the tier name for a score.
     */
    public static function tier(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= config('prospecting.score_tiers.hot') => 'hot',
            $score >= config('prospecting.score_tiers.warm') => 'warm',
            default => 'cold',
        };
    }

    /**
     * @return array{0: float, 1: string}
     */
    protected function websiteNeed(Lead $lead): array
    {
        return blank($lead->website)
            ? [1.0, 'No website listed.']
            : [0.0, 'Already has a website.'];
    }

    /**
     * @return array{0: float, 1: string}
     */
    protected function websiteQuality(Lead $lead): array
    {
        if (blank($lead->website)) {
            return [1.0, 'Nothing online to compare against.'];
        }

        $audit = $lead->website_audit;

        if ($audit === null) {
            return [0.0, 'Run a website audit to measure this.'];
        }

        if (! empty($audit['error'])) {
            return [1.0, 'The website could not be reached.'];
        }

        $score = (int) ($audit['score'] ?? 100);

        return [(100 - $score) / 100, "Website audit score is {$score}/100."];
    }

    /**
     * @return array{0: float, 1: string}
     */
    protected function businessActivity(Lead $lead): array
    {
        $reviews = max(0, (int) $lead->reviews_count);
        $reviewRatio = min(1.0, log10($reviews + 1) / log10(201));

        $ratingRatio = match (true) {
            $lead->rating === null => 0.25,
            $lead->rating >= 4.5 => 1.0,
            $lead->rating >= 4.0 => 0.75,
            $lead->rating >= 3.5 => 0.5,
            default => 0.25,
        };

        $rating = $lead->rating === null ? 'no rating' : "{$lead->rating} stars";

        return [($reviewRatio * 0.6) + ($ratingRatio * 0.4), "{$reviews} reviews, {$rating}."];
    }

    /**
     * @return array{0: float, 1: string}
     */
    protected function contactability(Lead $lead): array
    {
        $phone = filled($lead->phone);
        $email = filled($lead->email);
        $address = filled($lead->address);

        $ratio = ($phone ? 7 : 0) + ($email ? 5 : 0) + ($address ? 3 : 0);

        $missing = collect(['phone' => $phone, 'email' => $email, 'address' => $address])
            ->reject()
            ->keys();

        return [
            $ratio / 15,
            $missing->isEmpty() ? 'Phone, email and address available.' : 'Missing: '.$missing->implode(', ').'.',
        ];
    }

    /**
     * @return array{0: float, 1: string}
     */
    protected function profileFit(Lead $lead): array
    {
        $ratio = (filled($lead->category) ? 0.5 : 0) + (filled($lead->city) ? 0.5 : 0);

        return [$ratio, $ratio === 1.0 ? 'Category and city known.' : 'Category or city is missing.'];
    }
}

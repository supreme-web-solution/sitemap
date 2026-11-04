<?php

namespace App\Services\SiteBuilder;

use App\Data\SiteEditPlan;
use App\Models\Site;

/**
 * Classify chat instructions for offline vs generative-AI requirements.
 */
class SiteEditIntent
{
    public static function wantsServiceImages(string $instruction): bool
    {
        $lower = strtolower($instruction);

        if (! preg_match('/\bservic/i', $lower)) {
            return false;
        }

        return preg_match('/\b(images?|photos?|pictures?|illustrations?|icons?)\b/i', $instruction) === 1
            && preg_match('/\b(each|every|unique|per service|for those|for each)\b/i', $instruction) === 1;
    }

    public static function wantsHeroPhotoBackground(string $instruction): bool
    {
        if (! preg_match('/\bhero\b/i', $instruction)) {
            return false;
        }

        return preg_match('/\b(background\s*(image|photo|picture)|hero\s*(image|photo)|photo\s*background|not\s*(plain|plan|solid|blank)\s*background)\b/i', $instruction) === 1;
    }

    /**
     * True when the user asked for more than a single hero photo (layout, services, copy, etc.).
     */
    public static function wantsSiteWorkBeyondHeroPhoto(string $instruction): bool
    {
        if (self::wantsServiceImages($instruction)) {
            return true;
        }

        $parts = preg_split('/(?:\n+|;\s*|\d+\.\s+)/', $instruction) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts)));

        if (count($parts) >= 2) {
            return true;
        }

        return preg_match('/\b(layout|overlay|full[\s-]?width|variant|css|headline|tagline|button|footer|header|gallery)\b/i', $instruction) === 1
            && ! preg_match('/^\s*(regenerate|replace|change|update|new|better)\b.{0,40}\b(hero|background)\b.{0,20}\b(image|photo|picture)\b/i', $instruction);
    }

    public static function wantsFullWidthHero(string $instruction): bool
    {
        return preg_match('/\b(full[\s-]?width|edge[\s-]?to[\s-]?edge|full[\s-]?bleed)\b/i', $instruction) === 1
            && preg_match('/\bhero\b/i', $instruction) === 1;
    }

    /**
     * Whether this plan + instruction can finish without a live Anthropic call.
     */
    public static function canCompleteOffline(SiteEditPlan $plan, string $instruction, Site $site): bool
    {
        if ($plan->wantsDocumentOps() && $plan->documentOps !== []) {
            return true;
        }

        if (self::wantsServiceImages($instruction)) {
            if (! app(LeadPhotoGenerator::class)->configured()) {
                return false;
            }

            // Image API may work even when Anthropic text is out of credits.
            if ($plan->wantsDesignCss()) {
                return self::designTargetsCoveredByPresets($plan, $instruction);
            }

            return true;
        }

        if ($plan->wantsDesignCss()) {
            return self::designTargetsCoveredByPresets($plan, $instruction);
        }

        if ($plan->wantsCopyRevise()) {
            return SiteEditHeuristicOps::build($instruction, $site, null) !== []
                || preg_match('/(?:headline|tagline|button).*[\'\"].+[\'\"]/i', $instruction) === 1;
        }

        return false;
    }

    /**
     * @param  list<string>  $designTargets
     */
    public static function designTargetsCoveredByPresets(SiteEditPlan $plan, string $instruction): bool
    {
        if ($plan->designCssTargets === []) {
            return false;
        }

        foreach ($plan->designCssTargets as $target) {
            if (SiteSectionCssPresets::match($target, $instruction) === null) {
                return false;
            }
        }

        return true;
    }
}

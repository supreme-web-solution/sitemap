<?php

namespace App\Services\SiteBuilder;

use App\Data\SiteEditPlan;

/**
 * Adjust LLM plans so each kind of user intent uses the right pipeline (images vs CSS vs copy).
 */
class SiteEditPlanIntentFilter
{
    public static function apply(SiteEditPlan $plan, string $instruction): SiteEditPlan
    {
        $instruction = trim($instruction);

        if ($instruction === '') {
            return $plan;
        }

        $designTargets = $plan->designCssTargets;

        if (SiteEditIntent::wantsServiceImages($instruction)) {
            $designTargets = array_values(array_filter(
                $designTargets,
                fn (string $target) => $target !== 'services',
            ));
        }

        if (SiteEditIntent::wantsHeroPhotoBackground($instruction)) {
            $designTargets = array_values(array_filter(
                $designTargets,
                fn (string $target) => $target !== 'hero',
            ));
        }

        $copyInstruction = $plan->copyInstruction;

        if (SiteEditIntent::wantsServiceImages($instruction) || SiteEditIntent::wantsHeroPhotoBackground($instruction)) {
            $copyInstruction = $copyInstruction !== null
                ? self::trimServiceImageCopyNoise($copyInstruction)
                : null;

            if ($copyInstruction !== null && ! preg_match('/\b(headline|tagline|rewrite|copy|cta|about)\b/i', $copyInstruction)) {
                $copyInstruction = null;
            }
        }

        if (
            $designTargets === $plan->designCssTargets
            && $copyInstruction === $plan->copyInstruction
        ) {
            return $plan;
        }

        return new SiteEditPlan(
            reasoning: $plan->reasoning,
            copyInstruction: $copyInstruction,
            designCssTargets: $designTargets,
            designInstruction: $plan->designInstruction,
            sectionsAdd: $plan->sectionsAdd,
            sectionsRemove: $plan->sectionsRemove,
            layoutVariants: $plan->layoutVariants,
            documentOps: $plan->documentOps,
            fromLlm: $plan->fromLlm,
            aiProviderUnavailable: $plan->aiProviderUnavailable,
            aiProviderMessage: $plan->aiProviderMessage,
        );
    }

    protected static function trimServiceImageCopyNoise(string $copyInstruction): ?string
    {
        $lines = preg_split('/\r\n|\r|\n/', $copyInstruction) ?: [];
        $kept = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if (SiteEditIntent::wantsServiceImages($trimmed) && ! preg_match('/\b(headline|tagline|rewrite|copy|text)\b/i', $trimmed)) {
                continue;
            }

            $kept[] = $trimmed;
        }

        $joined = trim(implode("\n", $kept));

        return $joined !== '' ? $joined : null;
    }
}

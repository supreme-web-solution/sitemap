<?php

namespace App\Services\SiteBuilder;

use App\Models\Lead;
use App\Models\Site;
use Illuminate\Support\Str;

/**
 * Build OpenAI image prompts from business context + optional user hint.
 */
class SiteImagePromptBuilder
{
    public static function forHero(Lead $lead, Site $site, ?string $userHint = null): string
    {
        $hint = trim((string) $userHint);
        $category = trim((string) ($lead->category ?? 'local service business'));
        $city = trim((string) ($lead->city ?? ''));
        $name = trim((string) $lead->name);
        $variant = SectionVariants::clean($site->content['variants'] ?? [])['hero']
            ?? SectionVariants::CATALOG['hero']['default']
            ?? 'classic';

        $placement = match ($variant) {
            'overlay', 'photo-top' => 'wide cinematic hero background photograph (16:9 feel), room for dark gradient and white headline text overlaid on the left or center',
            'split' => 'wide professional photograph suitable for a hero column beside text',
            default => 'wide professional hero banner photograph for a business website header',
        };

        $subject = self::subjectForCategory($category);

        $prompt = implode(' ', array_filter([
            $placement.'.',
            "Subject: {$subject}.",
            $city !== '' ? "Setting suggests {$city} or a similar friendly local market." : null,
            "Business: {$name} ({$category}).",
            'Photorealistic, well-lit, trustworthy, no text, no logos, no watermark, no collage.',
            $hint !== '' ? 'User request: '.$hint : 'Avoid clichéd bathroom close-ups; prefer aspirational spaces customers want.',
        ]));

        return Str::limit($prompt, 900, '');
    }

    public static function forServiceCard(
        Lead $lead,
        string $serviceTitle,
        string $serviceDescription,
        ?string $userHint = null,
    ): string {
        $hint = trim((string) $userHint);
        $category = trim((string) ($lead->category ?? 'local service business'));
        $city = trim((string) ($lead->city ?? ''));
        $name = trim((string) $lead->name);

        $prompt = implode(' ', array_filter([
            'Professional marketing photograph for a website service card.',
            "Service: {$serviceTitle}.",
            $serviceDescription !== '' ? "Context: {$serviceDescription}." : null,
            "Business: {$name} ({$category}).",
            $city !== '' ? "Setting suggests {$city} or a similar local market." : null,
            'Photorealistic, well-lit, single clear subject, no text, no logos, no watermark, no collage.',
            $hint !== '' ? 'User request: '.Str::limit($hint, 300, '') : null,
        ]));

        return Str::limit($prompt, 900, '');
    }

    /**
     * Sharper, cleaner replacement photo for a hero or gallery slot.
     */
    public static function forImprove(Lead $lead, Site $site, ?string $userHint = null): string
    {
        $base = self::forHero($lead, $site, $userHint);

        return Str::limit(
            $base.' Ultra sharp focus, high resolution, crisp clean details, no blur, professional DSLR quality.',
            900,
            '',
        );
    }

    /**
     * Sharper, cleaner replacement photo for a service card.
     */
    public static function forServiceImprove(
        Lead $lead,
        string $serviceTitle,
        string $serviceDescription,
        ?string $userHint = null,
    ): string {
        $base = self::forServiceCard($lead, $serviceTitle, $serviceDescription, $userHint);

        return Str::limit(
            $base.' Ultra sharp focus, high resolution, crisp clean details, no blur, professional DSLR quality.',
            900,
            '',
        );
    }

    protected static function subjectForCategory(string $category): string
    {
        $lower = Str::lower($category);

        if (Str::contains($lower, ['clean', 'maid', 'janitor'])) {
            return 'a bright, modern living room or kitchen that looks freshly cleaned, subtle cleaning supplies, fresh and inviting';
        }

        if (Str::contains($lower, ['plumb'])) {
            return 'a professional plumber at work in a clean modern home, or a well-maintained bathroom fixture in context';
        }

        if (Str::contains($lower, ['dent', 'dental'])) {
            return 'a welcoming dental office reception or smiling patient environment, calm and professional';
        }

        if (Str::contains($lower, ['restaurant', 'food', 'cafe', 'catering'])) {
            return 'appetizing food or a warm restaurant interior, natural light';
        }

        if (Str::contains($lower, ['roof'])) {
            return 'professional roofing work — shingles, inspection, or repair in a realistic residential setting';
        }

        return 'a professional scene that matches a '.Str::lower($category).' business, appropriate for a marketing website hero';
    }
}

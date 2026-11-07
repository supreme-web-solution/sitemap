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
            'overlay' => 'wide cinematic hero background photograph (16:9 feel), room for dark gradient and white headline text overlaid on the left or center',
            'photo-top' => 'wide cinematic full-bleed hero photograph (16:9), subject framed mid-shot with breathing room at the bottom edge, no overlaid text needed',
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
     * A gallery photograph that is a different scene from the hero and from the other gallery shots.
     */
    public static function forGallery(Lead $lead, int $index, ?string $userHint = null, int $variation = 0): string
    {
        $hint = trim((string) $userHint);
        $scenes = self::galleryScenes($lead);
        $scene = $scenes[($index + $variation) % count($scenes)];
        $category = trim((string) ($lead->category ?? 'local service business'));
        $city = trim((string) ($lead->city ?? ''));
        $name = trim((string) $lead->name);

        return Str::limit(implode(' ', array_filter([
            'Gallery photograph for a local business website.',
            'This must be a different scene, subject, and camera angle from the hero photo and from the other gallery photos.',
            'Scene: '.$scene.'.',
            "Business: {$name} ({$category}).",
            $city !== '' ? "Setting suggests {$city} or a similar local neighbourhood." : null,
            'Photorealistic, well-lit, no text, no logos, no watermark, no collage.',
            $hint !== '' ? 'Also include: '.Str::limit($hint, 240, '') : null,
        ])), 900, '');
    }

    /**
     * Edit the attached photograph so it is sharper, without inventing a new scene.
     */
    public static function forSharpen(?string $userHint = null): string
    {
        $hint = trim((string) $userHint);

        return Str::limit(implode(' ', array_filter([
            'Edit the attached photograph in place.',
            'Keep the same people, faces, clothing, pose, vehicle, objects, background, and framing.',
            'Increase sharpness, clarity, and fine detail. Remove blur and noise.',
            'Do not add text, logos, watermarks, or new objects.',
            $hint !== '' ? 'Also: '.Str::limit($hint, 200, '') : null,
        ])), 900, '');
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

    /**
     * @return list<string>
     */
    protected static function galleryScenes(Lead $lead): array
    {
        $category = Str::lower((string) ($lead->category ?? ''));

        if (Str::contains($category, 'plumb')) {
            return [
                'a plumber repairing a kitchen faucet, hands and fixture in frame, bright modern kitchen',
                'a tidy utility room with a water heater and copper pipes, no people',
                'a closed service van parked on a tree-lined residential street, no posed portrait',
                'a finished modern bathroom with a new faucet and clean tile, no people',
                'a technician inspecting an under-sink drain with a flashlight',
                'an outdoor hose bib and neat exterior plumbing on a house wall',
                'tools and fittings laid out on a clean drop cloth before a repair',
                'a candid conversation in a hallway, not a posed portrait in front of a vehicle',
            ];
        }

        $subject = self::subjectForCategory((string) ($lead->category ?? 'local service business'));

        return [
            'a close, well-lit detail of the work: '.$subject,
            'a wide view of the workplace with nobody posing for the camera: '.$subject,
            'the finished result a customer would want to show: '.$subject,
            'tools and materials prepared before the job, orderly and realistic: '.$subject,
            'the work in progress, candid, with natural posture if anyone is present: '.$subject,
            'the outside of the job site as the crew arrives, no readable signage: '.$subject,
            'the space after the work is done, quiet and clean: '.$subject,
            'another room and another time of day for the same kind of job: '.$subject,
        ];
    }
}

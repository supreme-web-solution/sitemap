<?php

namespace App\Services\SiteBuilder;

use App\Enums\SiteSection;
use App\Models\Lead;
use App\Models\Site;
use App\Models\SiteChatMessage;

/**
 * User-visible chat transcript and factual site state for conversational turns.
 */
class SiteChatContext
{
    public static function transcript(Site $site, int $limit = 16): string
    {
        $lines = $site->chatMessages()
            ->where('status', '!=', SiteChatMessage::STATUS_PENDING)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (SiteChatMessage $message) => ($message->role === SiteChatMessage::ROLE_USER ? 'User' : 'Assistant')
                .': '.trim($message->body));

        if ($lines->isEmpty()) {
            return '';
        }

        return "Site editor chat (oldest first):\n".$lines->implode("\n\n---\n\n");
    }

    public static function facts(Site $site): string
    {
        $site->loadMissing('lead');
        $lead = $site->lead;
        $content = $site->content;
        $sections = array_values($content['sections'] ?? SiteSection::defaultOrder());
        $renderer = app(SiteRenderer::class);

        $photos = $lead->photoUrls();
        $hours = $lead->opening_hours ?? [];
        $reviews = $lead->testimonials();
        $visible = $renderer->visibleSections(
            $content,
            $photos,
            $hours,
            null,
            $reviews->count(),
            count(SiteProofPoints::areas($lead)),
        );

        $reviewsInPlan = in_array(SiteSection::Reviews->value, $sections, true);
        $reviewsVisible = in_array(SiteSection::Reviews->value, $visible, true);

        $heroVariant = SectionVariants::clean($content['variants'] ?? [])['hero']
            ?? SectionVariants::CATALOG['hero']['default']
            ?? 'classic';

        $lines = [
            SiteTemplateContext::forSite($site),
            'Hero image generation from chat: '.(app(LeadPhotoGenerator::class)->configured()
                ? 'available'
                : 'not available — tell the user to upload in Photos or try later; do not mention API vendors or env keys'),
            'Lead photos on site: '.count($photos).' (first photo = hero background when the layout uses one).',
            'Hero layout variant: '.$heroVariant,
            'Business: '.$lead->name,
            'Category: '.($lead->category ?: '(missing)'),
            'City: '.($lead->city ?: '(missing)'),
            'Address: '.($lead->address ?: '(missing)'),
            'Phone: '.($lead->phone ?: '(missing)'),
            'Email: '.($lead->email ?: '(missing)'),
            'Homepage sections (saved order): '.implode(' → ', $sections),
            'Sections actually rendered on preview: '.implode(' → ', $visible),
            'Customer reviews in plan: '.($reviewsInPlan ? 'yes' : 'no'),
            'Customer reviews visible on preview: '.($reviewsVisible ? 'yes' : 'no'),
            'Google reviews loaded for this lead: '.$reviews->count().' visible (rating '.($lead->rating ?? 'n/a').', count '.($lead->reviews_count ?? 0).')',
            'Menu pages enabled: '.implode(', ', array_values($content['pages'] ?? SitePages::DEFAULT)),
            'Services on site: '.collect($content['services'] ?? [])
                ->map(fn ($service) => is_array($service) ? (string) ($service['title'] ?? '') : '')
                ->filter()
                ->implode(', ') ?: '(none)',
            'Blog posts: '.$site->posts()->count().' total ('
                .$site->posts()->published()->count().' published)'
                .($site->posts()->exists()
                    ? ' — titles: '.$site->posts()->orderByDesc('id')->limit(8)->pluck('title')->implode('; ')
                    : ''),
        ];

        if ($reviewsInPlan && ! $reviewsVisible && $reviews->count() < Lead::MIN_TESTIMONIALS) {
            $lines[] = 'Note: reviews block is hidden until at least '.Lead::MIN_TESTIMONIALS.' reviews are fetched, unless the user explicitly enabled the section with fewer reviews.';
        }

        if ($reviewsInPlan && $reviews->count() === 0) {
            $lines[] = 'Action hint: user should open the lead Reviews panel and fetch Google reviews.';
        }

        $position = array_search(SiteSection::Reviews->value, $visible, true);
        if ($reviewsVisible && $position !== false) {
            $lines[] = 'Customer reviews appears as section '.($position + 1).' on the preview (scroll down from the hero).';
        } elseif ($reviewsInPlan) {
            $planPos = array_search(SiteSection::Reviews->value, $sections, true);
            if ($planPos !== false) {
                $lines[] = 'Customer reviews is configured at position '.($planPos + 1).' in the plan but may not render until reviews exist.';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Signals for the turn agent — no keyword rules; the model infers intent from text + vision.
     *
     * @param  list<string>  $attachmentPaths
     */
    public static function turnContext(string $instruction, array $attachmentPaths = []): string
    {
        $lines = [
            'Latest user message (verbatim): '.$instruction,
        ];

        $count = count($attachmentPaths);

        if ($count === 1) {
            $lines[] = 'An image file is attached to this user message. You can see it in this turn.';
            $lines[] = 'From the image and their words together, decide whether they want: (a) that file published on the live site (hero or gallery), (b) layout/copy/CSS changes inspired by the screenshot, (c) both, or (d) a conversational answer only.';
            $lines[] = 'Do not treat attachment as “always hero” or “always reference only” — infer from context like a product designer.';
        } elseif ($count > 1) {
            $lines[] = "{$count} image files are attached to this user message (in order). You can see all of them in this turn.";
            $lines[] = 'They may reference different sections or design references in one message — use every image that applies.';
            $lines[] = 'Decide per image whether it is a reference for layout/CSS, a file to publish on the site, or both; combine with their text.';
        } else {
            $lines[] = 'No image attachment on this message.';
        }

        $lines[] = 'Infer intent from meaning and chat history, not keyword matching.';

        return implode("\n", $lines);
    }
}

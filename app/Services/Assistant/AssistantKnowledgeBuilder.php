<?php

namespace App\Services\Assistant;

use App\Models\Lead;
use App\Models\Site;
use App\Models\SiteAssistant;
use App\Services\SiteBuilder\SiteLocales;
use Illuminate\Support\Str;

/**
 * Writes what the website assistant knows about the business from the site and the Google listing.
 *
 * The result is plain text the owner can read and edit, so it never contains anything we would not show a visitor.
 */
class AssistantKnowledgeBuilder
{
    /**
     * The most reviews quoted in the knowledge base.
     */
    protected const REVIEW_LIMIT = 5;

    public function build(Site $site): string
    {
        $site->loadMissing('lead');

        $lead = $site->lead;
        $content = SiteLocales::merge($site->content ?? [], SiteLocales::default($site->content ?? []));

        $sections = array_filter([
            $this->business($lead, $content),
            $this->contact($lead),
            $this->hours($lead),
            $this->services($content),
            $this->about($lead, $content),
            $this->reputation($lead),
            $this->faqs($content),
            $this->links($lead),
        ]);

        return Str::limit(implode("\n\n", $sections), SiteAssistant::MAX_KNOWLEDGE_LENGTH, '');
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function business(Lead $lead, array $content): string
    {
        $lines = ['BUSINESS', 'Name: '.$lead->name];

        if (filled($lead->category)) {
            $lines[] = 'Type of business: '.$lead->category;
        }

        foreach (['headline' => 'Headline', 'tagline' => 'Tagline'] as $key => $label) {
            if (filled($content[$key] ?? null)) {
                $lines[] = $label.': '.trim((string) $content[$key]);
            }
        }

        return implode("\n", $lines);
    }

    protected function contact(Lead $lead): ?string
    {
        $lines = [];

        $place = collect([$lead->address, $lead->city, $lead->state])->filter()->unique()->implode(', ');

        if ($place !== '') {
            $lines[] = 'Address and area served: '.$place;
        }

        $phones = collect([$lead->phone, ...($lead->additional_phones ?? [])])->filter()->unique()->implode(', ');

        if ($phones !== '') {
            $lines[] = 'Phone: '.$phones;
        }

        if (filled($lead->email)) {
            $lines[] = 'Email: '.$lead->email;
        }

        return $lines === [] ? null : "CONTACT AND LOCATION\n".implode("\n", $lines);
    }

    protected function hours(Lead $lead): ?string
    {
        $hours = $lead->opening_hours ?? [];

        if ($hours === []) {
            return null;
        }

        $lines = [];

        foreach ($hours as $day => $time) {
            $lines[] = "{$day}: {$time}";
        }

        return "OPENING HOURS\n".implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function services(array $content): ?string
    {
        $lines = [];

        foreach ((array) ($content['services'] ?? []) as $service) {
            $title = trim((string) ($service['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $detail = trim((string) ($service['description'] ?? ''));
            $lines[] = '- '.$title.($detail !== '' ? ': '.$detail : '');
        }

        return $lines === [] ? null : "SERVICES\n".implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function about(Lead $lead, array $content): ?string
    {
        $about = trim((string) ($content['about'] ?? ''));

        if ($about === '') {
            $about = trim((string) $lead->description);
        }

        return $about === '' ? null : "ABOUT THE BUSINESS\n".$about;
    }

    protected function reputation(Lead $lead): ?string
    {
        $lines = [];

        if ($lead->rating !== null && $lead->reviews_count > 0) {
            $lines[] = 'Google rating: '.number_format((float) $lead->rating, 1).' out of 5 from '.number_format($lead->reviews_count).' reviews.';
        }

        foreach ($lead->testimonials()->where('rating', '>=', 4)->take(self::REVIEW_LIMIT) as $review) {
            $lines[] = '- "'.Str::limit(trim($review->body), 220).'" ('.$review->author_name.')';
        }

        return $lines === [] ? null : "WHAT CUSTOMERS SAY\n".implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function faqs(array $content): ?string
    {
        $lines = [];

        foreach ((array) ($content['faqs'] ?? []) as $faq) {
            $question = trim((string) ($faq['question'] ?? ''));
            $answer = trim((string) ($faq['answer'] ?? ''));

            if ($question !== '' && $answer !== '') {
                $lines[] = "Q: {$question}\nA: {$answer}";
            }
        }

        return $lines === [] ? null : "FREQUENTLY ASKED QUESTIONS\n".implode("\n\n", $lines);
    }

    protected function links(Lead $lead): ?string
    {
        $lines = [];

        foreach (($lead->social_links ?? []) as $network => $url) {
            if (filled($url)) {
                $lines[] = ucfirst((string) $network).': '.$url;
            }
        }

        return $lines === [] ? null : "ONLINE\n".implode("\n", $lines);
    }
}

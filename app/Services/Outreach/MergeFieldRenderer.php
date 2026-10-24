<?php

namespace App\Services\Outreach;

use App\Models\Lead;
use App\Models\User;
use App\Services\Preview\PreviewLink;

class MergeFieldRenderer
{
    /**
     * The merge fields available in email templates, with a short description.
     *
     * @var array<string, string>
     */
    public const FIELDS = [
        'business_name' => 'The business name',
        'category' => 'The business category, e.g. Plumber',
        'city' => 'The business city',
        'rating' => 'The Google rating',
        'preview_url' => 'The link to the generated website preview',
        'top_issue' => 'The most important issue found by the website audit',
        'sender_name' => 'Your name',
        'team_name' => 'Your workspace name',
    ];

    /**
     * Replace merge fields in the text with values from the lead.
     */
    public function render(string $text, Lead $lead, User $sender): string
    {
        $values = $this->values($lead, $sender);

        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn (array $match) => $values[$match[1]] ?? $match[0],
            $text,
        ) ?? $text;
    }

    /**
     * Get the names of unknown merge fields used in the text.
     *
     * @return list<string>
     */
    public function unknownFields(string $text): array
    {
        preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $text, $matches);

        return array_values(array_diff(array_unique($matches[1]), array_keys(self::FIELDS)));
    }

    /**
     * Get the merge field values for a lead.
     *
     * @return array<string, string>
     */
    public function values(Lead $lead, User $sender): array
    {
        $lead->loadMissing('site:id,lead_id,team_id,slug', 'team:id,name');

        $topIssue = collect($lead->website_audit['checks'] ?? [])
            ->firstWhere('status', 'fail')['detail'] ?? '';

        return [
            'business_name' => $lead->name,
            'category' => $lead->category ?? 'business',
            'city' => $lead->city ?? '',
            'rating' => $lead->rating !== null ? (string) $lead->rating : '',
            'preview_url' => $lead->site ? PreviewLink::page($lead->site) : '',
            'top_issue' => $topIssue,
            'sender_name' => $sender->name,
            'team_name' => $lead->team->name,
        ];
    }
}

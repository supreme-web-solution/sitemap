<?php

namespace App\Enums;

enum UsageMetric: string
{
    case LeadSearches = 'lead_searches';
    case SiteGenerations = 'site_generations';
    case ReviewFetches = 'review_fetches';
    case Audits = 'audits';
    case Emails = 'emails';
    case Messages = 'messages';
    case AiEdits = 'ai_edits';
    case Leads = 'leads';
    case Sites = 'sites';
    case Domains = 'domains';
    case Members = 'members';

    /**
     * Whether the allowance resets every month, rather than capping what the team holds in total.
     */
    public function isMonthly(): bool
    {
        return match ($this) {
            self::Leads, self::Sites, self::Domains, self::Members => false,
            default => true,
        };
    }

    /**
     * Get the display label for the metric.
     */
    public function label(): string
    {
        return match ($this) {
            self::LeadSearches => 'Lead searches',
            self::SiteGenerations => 'Website generations',
            self::ReviewFetches => 'Review fetches',
            self::Audits => 'Website audits',
            self::Emails => 'Outreach emails',
            self::Messages => 'SMS and WhatsApp messages',
            self::AiEdits => 'AI edits',
            self::Leads => 'Saved leads',
            self::Sites => 'Websites',
            self::Domains => 'Custom domains',
            self::Members => 'Team members',
        };
    }
}
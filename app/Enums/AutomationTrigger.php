<?php

namespace App\Enums;

enum AutomationTrigger: string
{
    case LeadCreated = 'lead_created';
    case SiteGenerated = 'site_generated';
    case OutreachSent = 'outreach_sent';
    case ProposalSent = 'proposal_sent';
    case InvoicePaid = 'invoice_paid';
    case ScheduledAudit = 'scheduled_audit';
    case Manual = 'manual';
    case CommandCenter = 'command_center';

    public function label(): string
    {
        return match ($this) {
            self::LeadCreated => 'New lead saved',
            self::SiteGenerated => 'Website generated',
            self::OutreachSent => 'Outreach sent',
            self::ProposalSent => 'Proposal sent',
            self::InvoicePaid => 'Invoice paid',
            self::ScheduledAudit => 'Scheduled website audit',
            self::Manual => 'Manual run',
            self::CommandCenter => 'Command center',
        };
    }
}

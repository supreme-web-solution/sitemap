<?php

namespace App\Enums;

enum LeadActivityType: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case Note = 'note';
    case SiteGenerated = 'site_generated';
    case WebsiteAudited = 'website_audited';
    case EmailSent = 'email_sent';
    case MarkedContacted = 'marked_contacted';
    case FollowUpScheduled = 'follow_up_scheduled';
    case PreviewInterest = 'preview_interest';
    case InvoiceSent = 'invoice_sent';
    case InvoicePaid = 'invoice_paid';
    case InvoiceReminder = 'invoice_reminder';
    case ProposalSent = 'proposal_sent';
    case ProposalAccepted = 'proposal_accepted';
    case ProposalDeclined = 'proposal_declined';
    case SiteApproved = 'site_approved';
    case ClientFeedback = 'client_feedback';
    case MessageSent = 'message_sent';
    case MessageReceived = 'message_received';
    case SiteEdited = 'site_edited';

    /**
     * Get the display label for the activity type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Lead added',
            self::StatusChanged => 'Stage changed',
            self::Note => 'Note',
            self::SiteGenerated => 'Website generated',
            self::WebsiteAudited => 'Website audited',
            self::EmailSent => 'Email sent',
            self::MarkedContacted => 'Marked as contacted',
            self::FollowUpScheduled => 'Follow-up scheduled',
            self::PreviewInterest => 'Prospect replied from preview',
            self::InvoiceSent => 'Invoice sent',
            self::InvoicePaid => 'Invoice paid',
            self::InvoiceReminder => 'Invoice reminder sent',
            self::ProposalSent => 'Proposal sent',
            self::ProposalAccepted => 'Proposal accepted',
            self::ProposalDeclined => 'Proposal declined',
            self::SiteApproved => 'Client approved the website',
            self::ClientFeedback => 'Client feedback',
            self::MessageSent => 'Message sent',
            self::MessageReceived => 'Message received',
            self::SiteEdited => 'Website edited',
        };
    }
}

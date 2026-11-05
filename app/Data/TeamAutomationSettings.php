<?php

namespace App\Data;

final readonly class TeamAutomationSettings
{
    /**
     * @param  list<int>  $defaultPackageIds
     */
    public function __construct(
        public bool $enabled = true,
        public ?int $defaultSequenceId = null,
        public bool $enrollSequenceOnLeadCreated = false,
        public bool $auditWebsiteOnLeadCreated = true,
        public bool $queueSiteOnLeadCreated = false,
        public bool $draftProposalOnSiteGenerated = false,
        public array $defaultPackageIds = [],
        public bool $moveInterestedWhenPreviewInOutreach = true,
        public bool $moveInterestedOnProposalSent = true,
        public bool $onboardingOnInvoicePaid = true,
        public int $reauditWebsitesEveryDays = 0,
    ) {}

    /**
     * @param  array<string, mixed>|null  $raw
     */
    public static function from(?array $raw): self
    {
        if ($raw === null || $raw === []) {
            return new self;
        }

        $packages = $raw['default_package_ids'] ?? $raw['defaultPackageIds'] ?? [];

        if (! is_array($packages)) {
            $packages = [];
        }

        return new self(
            enabled: (bool) ($raw['enabled'] ?? true),
            defaultSequenceId: filled($raw['default_sequence_id'] ?? $raw['defaultSequenceId'] ?? null)
                ? (int) ($raw['default_sequence_id'] ?? $raw['defaultSequenceId'])
                : null,
            enrollSequenceOnLeadCreated: (bool) ($raw['enroll_sequence_on_lead_created'] ?? $raw['enrollSequenceOnLeadCreated'] ?? false),
            auditWebsiteOnLeadCreated: (bool) ($raw['audit_website_on_lead_created'] ?? $raw['auditWebsiteOnLeadCreated'] ?? true),
            queueSiteOnLeadCreated: (bool) ($raw['queue_site_on_lead_created'] ?? $raw['queueSiteOnLeadCreated'] ?? false),
            draftProposalOnSiteGenerated: (bool) ($raw['draft_proposal_on_site_generated'] ?? $raw['draftProposalOnSiteGenerated'] ?? false),
            defaultPackageIds: array_values(array_map(intval(...), $packages)),
            moveInterestedWhenPreviewInOutreach: (bool) ($raw['move_interested_when_preview_in_outreach'] ?? $raw['moveInterestedWhenPreviewInOutreach'] ?? true),
            moveInterestedOnProposalSent: (bool) ($raw['move_interested_on_proposal_sent'] ?? $raw['moveInterestedOnProposalSent'] ?? true),
            onboardingOnInvoicePaid: (bool) ($raw['onboarding_on_invoice_paid'] ?? $raw['onboardingOnInvoicePaid'] ?? true),
            reauditWebsitesEveryDays: max(0, (int) ($raw['reaudit_websites_every_days'] ?? $raw['reauditWebsitesEveryDays'] ?? 0)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'default_sequence_id' => $this->defaultSequenceId,
            'enroll_sequence_on_lead_created' => $this->enrollSequenceOnLeadCreated,
            'audit_website_on_lead_created' => $this->auditWebsiteOnLeadCreated,
            'queue_site_on_lead_created' => $this->queueSiteOnLeadCreated,
            'draft_proposal_on_site_generated' => $this->draftProposalOnSiteGenerated,
            'default_package_ids' => $this->defaultPackageIds,
            'move_interested_when_preview_in_outreach' => $this->moveInterestedWhenPreviewInOutreach,
            'move_interested_on_proposal_sent' => $this->moveInterestedOnProposalSent,
            'onboarding_on_invoice_paid' => $this->onboardingOnInvoicePaid,
            'reaudit_websites_every_days' => $this->reauditWebsitesEveryDays,
        ];
    }
}

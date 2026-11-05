<?php

namespace App\Ai\Tools\CommandCenter;

use App\Support\CommandCenter\CommandCenterContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DescribeWorkflowAutomation implements Tool
{
    public function __construct(protected CommandCenterContext $context) {}

    public function description(): Stringable|string
    {
        return 'Read the team background automation settings (set-and-forget workflow engine, separate from this chat).';
    }

    public function handle(Request $request): Stringable|string
    {
        $settings = $this->context->team->automationSettings();

        $lines = [
            'enabled: '.($settings->enabled ? 'yes' : 'no'),
            'enroll_sequence_on_lead_created: '.($settings->enrollSequenceOnLeadCreated ? 'yes' : 'no'),
            'audit_website_on_lead_created: '.($settings->auditWebsiteOnLeadCreated ? 'yes' : 'no'),
            'queue_site_on_lead_created: '.($settings->queueSiteOnLeadCreated ? 'yes' : 'no'),
            'draft_proposal_on_site_generated: '.($settings->draftProposalOnSiteGenerated ? 'yes' : 'no'),
            'reaudit_websites_every_days: '.$settings->reauditWebsitesEveryDays,
            'default_sequence_id: '.($settings->defaultSequenceId ?? 'none'),
        ];

        return "Background automation (Automations page):\n".implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

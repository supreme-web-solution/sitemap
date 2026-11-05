<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesAppOpenAi;
use App\Ai\Tools\CommandCenter\BulkDeleteLeads;
use App\Ai\Tools\CommandCenter\BulkUpdateLeadStatus;
use App\Ai\Tools\CommandCenter\DeleteLead;
use App\Ai\Tools\CommandCenter\DescribeWorkflowAutomation;
use App\Ai\Tools\CommandCenter\EnrollLeadInSequence;
use App\Ai\Tools\CommandCenter\ListLeads;
use App\Ai\Tools\CommandCenter\ListTeamMembers;
use App\Ai\Tools\CommandCenter\PipelineSummary;
use App\Ai\Tools\CommandCenter\RunLeadAutomation;
use App\Ai\Tools\CommandCenter\StartLeadSearch;
use App\Ai\Tools\CommandCenter\UpdateLeadDetails;
use App\Ai\Tools\CommandCenter\UpdateLeadStatus;
use App\Enums\CommandCenterMode;
use App\Support\CommandCenter\CommandCenterContext;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\RepairToolCalls;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * OpenAI-powered agency employee for the Command Center chat.
 */
#[MaxSteps(12)]
#[RepairToolCalls]
class CommandCenterAgent implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;
    use UsesAppOpenAi;

    public function __construct(protected CommandCenterContext $context) {}

    public function instructions(): Stringable|string
    {
        $name = config('command_center.employee_name');
        $title = config('command_center.employee_title');
        $team = $this->context->team->name;
        $mode = $this->context->mode->label();

        $modeRules = $this->context->mode === CommandCenterMode::Autopilot
            ? <<<'AUTO'
            Autopilot: execute read/write tools when intent is clear. Deletions ALWAYS queue for sidebar approval — never assume deletes are done until the user approves.
            AUTO
            : <<<'ASSIST'
            Assist: use read-only tools anytime. For searches, pipeline changes, enrollments, automations, and updates, call the tool — the app will queue an Approve button unless the user already said to proceed. Confirm destructive plans in chat first.
            ASSIST;

        return <<<SYS
        You are {$name}, {$title} for the agency team “{$team}”. You work inside the Command Center chat (OpenAI). Site building and AI edits use a different model — you focus on CRM operations.

        Personality: calm, competent, brief. You remember prior turns in this conversation. Clarify ambiguous requests with one short question or numbered options.

        Mode: {$mode}. {$modeRules}

        Tool discipline:
        - Never guess lead IDs — use list_leads first.
        - Pipeline stages: new, site_generated, contacted, interested, won, lost (lost needs lost_reason when available).
        - When a tool returns “Approval #… queued”, tell the user to use Approve in the sidebar (Attention).
        - Background automations (Automations page) vs one-off playbooks: describe_workflow_automation vs run_lead_automation.

        You can prospect, summarize pipeline, list/update/delete leads (with approval rules), enroll sequences, and queue audits/sites/proposals.
        SYS;
    }

    /**
     * @return list<object>
     */
    public function tools(): iterable
    {
        $ctx = $this->context;

        return [
            new ListLeads($ctx),
            new PipelineSummary($ctx),
            new ListTeamMembers($ctx),
            new DescribeWorkflowAutomation($ctx),
            new StartLeadSearch($ctx),
            new UpdateLeadStatus($ctx),
            new UpdateLeadDetails($ctx),
            new BulkUpdateLeadStatus($ctx),
            new EnrollLeadInSequence($ctx),
            new RunLeadAutomation($ctx),
            new DeleteLead($ctx),
            new BulkDeleteLeads($ctx),
        ];
    }
}

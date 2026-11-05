<?php

namespace App\Services\Automation;

use App\Data\TeamAutomationSettings;
use App\Enums\AutomationTrigger;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\ProposalStatus;
use App\Enums\SiteGenerationStatus;
use App\Enums\UsageMetric;
use App\Jobs\AuditLeadWebsite;
use App\Jobs\GenerateLeadSite;
use App\Models\AutomationRun;
use App\Models\FollowUpSequence;
use App\Models\Lead;
use App\Models\Package;
use App\Models\Proposal;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\UsageMeter;
use App\Services\Outreach\SequenceEnroller;
use App\Services\Preview\PreviewLink;
use Illuminate\Support\Str;

class AgencyWorkflowEngine
{
    public function __construct(
        protected SequenceEnroller $sequences,
        protected UsageMeter $usage,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    public function run(
        Team $team,
        AutomationTrigger $trigger,
        Lead $lead,
        array $context = [],
        ?User $actor = null,
    ): array {
        $settings = $team->automationSettings();

        if (! $settings->enabled && $trigger !== AutomationTrigger::Manual && $trigger !== AutomationTrigger::CommandCenter) {
            return [];
        }

        $actions = match ($trigger) {
            AutomationTrigger::LeadCreated => $this->onLeadCreated($team, $lead, $settings),
            AutomationTrigger::SiteGenerated => $this->onSiteGenerated($team, $lead, $settings),
            AutomationTrigger::OutreachSent => $this->onOutreachSent($lead, $settings, $context),
            AutomationTrigger::ProposalSent => $this->onProposalSent($lead, $settings),
            AutomationTrigger::InvoicePaid => $this->onInvoicePaid($lead, $settings),
            AutomationTrigger::ScheduledAudit => $this->onScheduledAudit($lead),
            AutomationTrigger::Manual, AutomationTrigger::CommandCenter => $this->onManual($team, $lead, $settings, $context),
        };

        if ($actions === []) {
            return [];
        }

        $summary = Str::limit(implode('; ', $actions), 480);

        AutomationRun::query()->create([
            'team_id' => $team->id,
            'lead_id' => $lead->id,
            'user_id' => $actor?->id,
            'trigger' => $trigger->value,
            'summary' => $summary,
            'actions' => $actions,
        ]);

        $lead->recordActivity(
            LeadActivityType::Workflow,
            __('Automation (:trigger): :summary', [
                'trigger' => $trigger->label(),
                'summary' => $summary,
            ]),
            ['trigger' => $trigger->value, 'actions' => $actions],
            $actor?->id,
        );

        return $actions;
    }

    /**
     * @return list<string>
     */
    protected function onLeadCreated(Team $team, Lead $lead, TeamAutomationSettings $settings): array
    {
        $actions = [];

        if ($settings->enrollSequenceOnLeadCreated && $settings->defaultSequenceId !== null) {
            $actions = array_merge($actions, $this->enrollLead($team, $lead, $settings->defaultSequenceId));
        }

        if ($settings->auditWebsiteOnLeadCreated && filled($lead->website)) {
            AuditLeadWebsite::dispatch($lead);
            $actions[] = __('Queued website audit');
        }

        if ($settings->queueSiteOnLeadCreated && $lead->site === null) {
            if ($this->usage->allows($team, UsageMetric::Generations)) {
                $lead->forceFill(['site_generation_status' => SiteGenerationStatus::Queued])->save();
                GenerateLeadSite::dispatch($lead);
                $actions[] = __('Queued AI website generation');
            } else {
                $actions[] = __('Skipped site generation (plan limit)');
            }
        }

        return $actions;
    }

    /**
     * @return list<string>
     */
    protected function onSiteGenerated(Team $team, Lead $lead, TeamAutomationSettings $settings): array
    {
        $actions = [];

        if ($settings->draftProposalOnSiteGenerated && $settings->defaultPackageIds !== []) {
            $created = $this->createDraftProposalFromPackages($team, $lead, $settings->defaultPackageIds);

            if ($created !== null) {
                $actions[] = __('Created draft proposal :number', ['number' => $created]);
            }
        }

        if ($settings->defaultSequenceId !== null
            && $lead->enrollments()->where('status', EnrollmentStatus::Active)->doesntExist()
            && ! in_array($lead->status, [LeadStatus::Won, LeadStatus::Lost], true)) {
            $actions = array_merge($actions, $this->enrollLead($team, $lead, $settings->defaultSequenceId));
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    protected function onOutreachSent(Lead $lead, TeamAutomationSettings $settings, array $context): array
    {
        if (! $settings->moveInterestedWhenPreviewInOutreach) {
            return [];
        }

        $body = (string) ($context['body'] ?? '');
        $previewUrl = $lead->site ? PreviewLink::page($lead->site, internal: false) : '';

        if ($previewUrl === '' || ! str_contains($body, $previewUrl)) {
            return [];
        }

        if (! in_array($lead->status, [LeadStatus::New, LeadStatus::SiteGenerated, LeadStatus::Contacted], true)) {
            return [];
        }

        $lead->forceFill(['status' => LeadStatus::Interested])->save();

        return [__('Moved to Interested (preview link was in outreach)')];
    }

    /**
     * @return list<string>
     */
    protected function onProposalSent(Lead $lead, TeamAutomationSettings $settings): array
    {
        if (! $settings->moveInterestedOnProposalSent) {
            return [];
        }

        if (! in_array($lead->status, [LeadStatus::New, LeadStatus::SiteGenerated, LeadStatus::Contacted], true)) {
            return [];
        }

        $lead->forceFill(['status' => LeadStatus::Interested])->save();

        return [__('Moved to Interested (proposal sent)')];
    }

    /**
     * @return list<string>
     */
    protected function onInvoicePaid(Lead $lead, TeamAutomationSettings $settings): array
    {
        if (! $settings->onboardingOnInvoicePaid) {
            return [];
        }

        $actions = [];

        if ($lead->portal_token === null) {
            $lead->forceFill(['portal_token' => Str::random(40)])->save();
            $actions[] = __('Client portal link enabled');
        }

        $tasks = [
            ['title' => __('Email the client their portal link'), 'due_on' => today()],
            ['title' => __('Confirm domain and publish plan'), 'due_on' => today()->addDay()],
            ['title' => __('Schedule kickoff or handoff call'), 'due_on' => today()->addDays(3)],
        ];

        foreach ($tasks as $task) {
            if ($lead->tasks()->where('title', $task['title'])->doesntExist()) {
                $lead->tasks()->create([
                    'team_id' => $lead->team_id,
                    'title' => $task['title'],
                    'due_on' => $task['due_on'],
                ]);
            }
        }

        $actions[] = __('Added onboarding tasks');

        return $actions;
    }

    /**
     * @return list<string>
     */
    protected function onScheduledAudit(Lead $lead): array
    {
        if (! filled($lead->website)) {
            return [];
        }

        AuditLeadWebsite::dispatch($lead);

        return [__('Queued scheduled website re-audit')];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    protected function onManual(Team $team, Lead $lead, TeamAutomationSettings $settings, array $context): array
    {
        $actions = [];
        $steps = $context['steps'] ?? [];

        if (! is_array($steps)) {
            $steps = [];
        }

        if (in_array('enroll_sequence', $steps, true) && $settings->defaultSequenceId !== null) {
            $actions = array_merge($actions, $this->enrollLead($team, $lead, $settings->defaultSequenceId));
        }

        if (in_array('audit', $steps, true) && filled($lead->website)) {
            AuditLeadWebsite::dispatch($lead);
            $actions[] = __('Queued website audit');
        }

        if (in_array('generate_site', $steps, true) && $lead->site === null && $this->usage->allows($team, UsageMetric::Generations)) {
            $lead->forceFill(['site_generation_status' => SiteGenerationStatus::Queued])->save();
            GenerateLeadSite::dispatch($lead);
            $actions[] = __('Queued AI website generation');
        }

        if (in_array('draft_proposal', $steps, true) && $settings->defaultPackageIds !== []) {
            $number = $this->createDraftProposalFromPackages($team, $lead, $settings->defaultPackageIds);

            if ($number !== null) {
                $actions[] = __('Created draft proposal :number', ['number' => $number]);
            }
        }

        if (in_array('full_playbook', $steps, true)) {
            $actions = array_merge($actions, $this->onLeadCreated($team, $lead, $settings));

            if ($lead->site !== null) {
                $actions = array_merge($actions, $this->onSiteGenerated($team, $lead, $settings));
            }
        }

        return $actions;
    }

    /**
     * @return list<string>
     */
    protected function enrollLead(Team $team, Lead $lead, int $sequenceId): array
    {
        $sequence = FollowUpSequence::query()
            ->where('team_id', $team->id)
            ->whereKey($sequenceId)
            ->first();

        if ($sequence === null) {
            return [__('Sequence :id not found', ['id' => $sequenceId])];
        }

        $skip = $this->sequences->enrollOrSkip($team, $lead, $sequence);

        if ($skip === null) {
            return [__('Enrolled in sequence :name', ['name' => $sequence->name])];
        }

        return [__('Sequence skipped: :reason', ['reason' => $skip])];
    }

    /**
     * @param  list<int>  $packageIds
     */
    protected function createDraftProposalFromPackages(Team $team, Lead $lead, array $packageIds): ?string
    {
        if ($lead->proposals()->whereIn('status', [ProposalStatus::Draft, ProposalStatus::Sent])->exists()) {
            return null;
        }

        $packages = Package::query()
            ->where('team_id', $team->id)
            ->whereIn('id', $packageIds)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($packages->isEmpty()) {
            return null;
        }

        $proposal = $team->proposals()->make([
            'lead_id' => $lead->id,
            'title' => __('Website package for :business', ['business' => $lead->name]),
        ]);
        $proposal->forceFill([
            'number' => Proposal::nextNumber($team),
            'status' => ProposalStatus::Draft,
            'valid_until' => now()->addDays(14),
        ])->save();

        foreach ($packages as $package) {
            $proposal->items()->create([
                'description' => $package->name,
                'quantity' => 1,
                'unit_cents' => $package->unit_cents,
                'interval' => $package->interval,
            ]);
        }

        return $proposal->number;
    }
}

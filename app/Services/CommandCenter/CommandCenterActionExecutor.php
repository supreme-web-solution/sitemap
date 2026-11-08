<?php

namespace App\Services\CommandCenter;

use App\Enums\AutomationTrigger;
use App\Enums\LeadSearchStatus;
use App\Enums\LeadStatus;
use App\Enums\LostReason;
use App\Enums\UsageMetric;
use App\Enums\WebsiteFilter;
use App\Exceptions\PlanLimitException;
use App\Jobs\RunAgencyWorkflow;
use App\Jobs\RunLeadSearch;
use App\Models\FollowUpSequence;
use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\UsageMeter;
use App\Services\Outreach\SequenceEnroller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommandCenterActionExecutor
{
    public function __construct(
        protected UsageMeter $usage,
        protected SequenceEnroller $sequences,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function run(string $action, array $payload, Team $team, User $user): string
    {
        return match ($action) {
            'start_lead_search' => $this->startLeadSearch($payload, $team, $user),
            'run_lead_automation' => $this->runLeadAutomation($payload, $team, $user),
            'update_lead_status' => $this->updateLeadStatus($payload, $team),
            'update_lead_details' => $this->updateLeadDetails($payload, $team),
            'bulk_update_lead_status' => $this->bulkUpdateLeadStatus($payload, $team),
            'enroll_lead_sequence' => $this->enrollLeadSequence($payload, $team),
            'delete_lead' => $this->deleteLead($payload, $team),
            'bulk_delete_leads' => $this->bulkDeleteLeads($payload, $team),
            default => __('Unknown action :action.', ['action' => $action]),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function startLeadSearch(array $payload, Team $team, User $user): string
    {
        $data = Validator::make($payload, [
            'keyword' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:150'],
            'website_filter' => ['nullable', Rule::enum(WebsiteFilter::class)],
            'minimum_rating' => ['nullable', 'numeric'],
            'max_results' => ['nullable', 'integer', 'min:1', 'max:100'],
        ])->validate();

        try {
            $this->usage->consume($team, UsageMetric::LeadSearches, 1);
        } catch (PlanLimitException) {
            return __('Lead search quota exhausted for this billing period.');
        }

        $filter = WebsiteFilter::tryFrom((string) ($data['website_filter'] ?? ''))
            ?? WebsiteFilter::WithoutWebsite;

        $search = $team->leadSearches()->create([
            'keyword' => $data['keyword'],
            'location' => $data['location'],
            'website_filter' => $filter,
            'minimum_rating' => isset($data['minimum_rating']) ? (float) $data['minimum_rating'] : null,
            'max_results' => (int) ($data['max_results'] ?? 20),
            'user_id' => $user->id,
            'notify_command_center' => true,
            'status' => LeadSearchStatus::Pending,
        ]);

        RunLeadSearch::dispatch($search);

        return __('Started lead search #:id for “:keyword” in :location.', [
            'id' => $search->id,
            'keyword' => $data['keyword'],
            'location' => $data['location'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function runLeadAutomation(array $payload, Team $team, User $user): string
    {
        $leadId = (int) ($payload['lead_id'] ?? 0);
        $steps = $payload['steps'] ?? [];

        if ($leadId < 1 || ! is_array($steps) || $steps === []) {
            return __('Invalid automation payload.');
        }

        $lead = Lead::query()->where('team_id', $team->id)->whereKey($leadId)->first();

        if ($lead === null) {
            return __('Lead #:id not found.', ['id' => $leadId]);
        }

        RunAgencyWorkflow::dispatchForLead(
            $lead,
            AutomationTrigger::CommandCenter,
            ['steps' => array_values($steps)],
            $user->id,
        );

        return __('Queued automation for :name.', ['name' => $lead->name]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function updateLeadStatus(array $payload, Team $team): string
    {
        $data = Validator::make($payload, [
            'lead_id' => ['required', 'integer'],
            'status' => ['required', Rule::enum(LeadStatus::class)],
            'lost_reason' => ['nullable', Rule::enum(LostReason::class)],
        ])->validate();

        $lead = Lead::query()->where('team_id', $team->id)->whereKey($data['lead_id'])->first();

        if ($lead === null) {
            return __('Lead not found.');
        }

        $status = LeadStatus::from($data['status']);

        if ($status === LeadStatus::Lost && blank($data['lost_reason'] ?? null)) {
            return __('lost_reason is required when marking lost.');
        }

        $lead->forceFill([
            'status' => $status,
            'lost_reason' => $status === LeadStatus::Lost
                ? LostReason::from($data['lost_reason'])
                : null,
        ])->save();

        return __('Updated :name to :status.', ['name' => $lead->name, 'status' => $status->value]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function updateLeadDetails(array $payload, Team $team): string
    {
        $data = Validator::make($payload, [
            'lead_id' => ['required', 'integer'],
            'deal_value' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'assigned_user_id' => ['nullable', 'integer'],
            'next_follow_up_at' => ['nullable', 'date'],
        ])->validate();

        $lead = Lead::query()->where('team_id', $team->id)->whereKey($data['lead_id'])->first();

        if ($lead === null) {
            return __('Lead not found.');
        }

        $updates = [];

        if (array_key_exists('deal_value', $data)) {
            $updates['deal_value'] = $data['deal_value'];
        }

        if (array_key_exists('notes', $data)) {
            $updates['notes'] = $data['notes'];
        }

        if (array_key_exists('next_follow_up_at', $data)) {
            $updates['next_follow_up_at'] = $data['next_follow_up_at'];
        }

        if (array_key_exists('assigned_user_id', $data) && filled($data['assigned_user_id'])) {
            $member = $team->memberships()->where('user_id', $data['assigned_user_id'])->exists();

            if (! $member) {
                return __('That user is not on the team.');
            }

            $updates['assigned_user_id'] = $data['assigned_user_id'];
        }

        if ($updates === []) {
            return __('No fields to update.');
        }

        $lead->forceFill($updates)->save();

        return __('Updated details for :name.', ['name' => $lead->name]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function bulkUpdateLeadStatus(array $payload, Team $team): string
    {
        $data = Validator::make($payload, [
            'lead_ids' => ['required', 'array', 'min:1', 'max:50'],
            'lead_ids.*' => ['integer'],
            'status' => ['required', Rule::enum(LeadStatus::class)],
        ])->validate();

        $status = LeadStatus::from($data['status']);

        $count = Lead::query()
            ->where('team_id', $team->id)
            ->whereIn('id', $data['lead_ids'])
            ->update(['status' => $status]);

        return __('Moved :count lead(s) to :status.', ['count' => $count, 'status' => $status->value]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function enrollLeadSequence(array $payload, Team $team): string
    {
        $data = Validator::make($payload, [
            'lead_id' => ['required', 'integer'],
            'sequence_id' => ['nullable', 'integer'],
        ])->validate();

        $lead = Lead::query()->where('team_id', $team->id)->whereKey($data['lead_id'])->first();

        if ($lead === null) {
            return __('Lead not found.');
        }

        $sequenceId = $data['sequence_id'] ?? $team->automationSettings()->defaultSequenceId;

        if ($sequenceId === null) {
            return __('No sequence_id and no default sequence configured.');
        }

        $sequence = FollowUpSequence::query()
            ->where('team_id', $team->id)
            ->whereKey($sequenceId)
            ->first();

        if ($sequence === null) {
            return __('Sequence not found.');
        }

        try {
            $this->sequences->enroll($team, $lead, $sequence);
        } catch (ValidationException $exception) {
            return collect($exception->errors())->flatten()->first() ?? __('Could not enroll.');
        }

        return __('Enrolled :name in :sequence.', ['name' => $lead->name, 'sequence' => $sequence->name]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function deleteLead(array $payload, Team $team): string
    {
        $leadId = (int) ($payload['lead_id'] ?? 0);
        $lead = Lead::query()->where('team_id', $team->id)->whereKey($leadId)->first();

        if ($lead === null) {
            return __('Lead not found.');
        }

        $name = $lead->name;
        $lead->delete();

        return __('Deleted lead :name.', ['name' => $name]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function bulkDeleteLeads(array $payload, Team $team): string
    {
        $data = Validator::make($payload, [
            'lead_ids' => ['required', 'array', 'min:1', 'max:50'],
            'lead_ids.*' => ['integer'],
        ])->validate();

        $count = Lead::query()
            ->where('team_id', $team->id)
            ->whereIn('id', $data['lead_ids'])
            ->delete();

        return __('Deleted :count lead(s).', ['count' => $count]);
    }
}

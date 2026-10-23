<?php

namespace App\Http\Controllers\Leads;

use App\Actions\Sites\DispatchSiteGeneration;
use App\Enums\LostReason;
use App\Enums\PlanFeature;
use App\Enums\SiteGenerationStatus;
use App\Enums\SiteVibe;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\UpdateLeadProfileRequest;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadTask;
use App\Models\Message;
use App\Models\SequenceEnrollment;
use App\Models\SiteConcept;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use App\Services\Outreach\DefaultEmailTemplates;
use App\Services\Outreach\DefaultFollowUpSequences;
use App\Services\Outreach\MergeFieldRenderer;
use App\Services\Prospecting\OpportunityScorer;
use App\Services\Prospecting\ProfileVerifier;
use App\Services\Prospecting\SalesAssistant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class LeadProfileController extends Controller
{
    /**
     * Show the owner review screen for a lead.
     */
    public function edit(Request $request, Team $currentTeam, Lead $lead, ProfileVerifier $verifier, DefaultEmailTemplates $defaults, DefaultFollowUpSequences $sequences, MergeFieldRenderer $merge, UsageMeter $usage, SalesAssistant $assistant): Response
    {
        $defaults->ensureFor($currentTeam);
        $sequences->ensureFor($currentTeam);

        $lead->load('site:id,lead_id,slug,approved_at');

        return Inertia::render('leads/Edit', [
            'lead' => [
                'id' => $lead->id,
                'name' => $lead->name,
                'category' => $lead->category,
                'description' => $lead->description,
                'phone' => $lead->phone,
                'email' => $lead->email,
                'address' => $lead->address,
                'city' => $lead->city,
                'rating' => $lead->rating,
                'reviewsCount' => $lead->reviews_count,
                'mapsUrl' => $lead->maps_url,
                'openingHours' => $lead->opening_hours ?? [],
                'vibe' => $lead->vibe?->value,
                'logoUrl' => $lead->logoUrl(),
                'photoUrls' => $lead->photoUrls(),
                'hasUploadedPhotos' => ! empty($lead->photo_paths),
                'detailsConfirmedAt' => $lead->details_confirmed_at?->toISOString(),
                'siteGenerationStatus' => $lead->site_generation_status->value,
                'siteGenerationError' => $lead->site_generation_error,
                'site' => $lead->site ? [
                    'id' => $lead->site->id,
                    'previewUrl' => route('sites.preview', $lead->site->slug),
                    'approvedAt' => $lead->site->approved_at?->toISOString(),
                ] : null,
                'portalUrl' => $lead->portalUrl(),
                'portalAvailable' => $usage->allows($currentTeam, PlanFeature::ClientPortal),
                'conceptsStatus' => ($lead->concepts_status ?? SiteGenerationStatus::Idle)->value,
                'status' => $lead->status->value,
                'lostReason' => $lead->lost_reason?->value,
                'assignedUserId' => $lead->assigned_user_id,
                'reviewsSummary' => $lead->reviews_summary,
                'competitorAudit' => $lead->competitor_audit,
                'competitorAuditedAt' => $lead->competitor_audited_at?->toISOString(),
                'website' => $lead->website,
                'dealValue' => $lead->deal_value,
                'nextFollowUpAt' => $lead->next_follow_up_at?->toDateString(),
                'lastContactedAt' => $lead->last_contacted_at?->toISOString(),
                'opportunityScore' => $lead->opportunity_score,
                'opportunityTier' => OpportunityScorer::tier($lead->opportunity_score),
                'opportunityBreakdown' => $lead->opportunity_breakdown ?? [],
                'websiteAudit' => $lead->website_audit,
                'websiteAuditedAt' => $lead->website_audited_at?->toISOString(),
                'websiteAuditStatus' => $lead->website_audit_status->value,
            ],
            'verification' => $verifier->verify($lead),
            'activities' => $lead->activities()
                ->with('user:id,name')
                ->latest('id')
                ->limit(40)
                ->get()
                ->map(fn (LeadActivity $activity) => [
                    'id' => $activity->id,
                    'type' => $activity->type->value,
                    'label' => $activity->type->label(),
                    'summary' => $activity->summary,
                    'data' => $activity->data,
                    'author' => $activity->user?->name,
                    'createdAt' => $activity->created_at->toISOString(),
                ]),
            'concepts' => $this->conceptsProps($currentTeam, $lead, $usage),
            'messaging' => $this->messagingProps($currentTeam, $lead, $usage),
            'emailTemplates' => $currentTeam->emailTemplates()->orderBy('id')->get(['id', 'name', 'subject', 'body']),
            'mergeValues' => $merge->values($lead, $request->user()),
            'vibes' => SiteVibe::options(),
            'days' => UpdateLeadProfileRequest::DAYS,
            'maxPhotos' => UpdateLeadProfileRequest::MAX_PHOTOS,
            'members' => $currentTeam->memberOptions(),
            'lostReasons' => LostReason::options(),
            'suggestions' => $assistant->suggest($lead->loadMissing(['site', 'proposals', 'invoices'])),
            'sequences' => $currentTeam->followUpSequences()->where('is_active', true)->orderBy('id')->get(['id', 'name']),
            'enrollments' => $lead->enrollments()->with('sequence:id,name')->latest('id')->get()->map(fn (SequenceEnrollment $enrollment) => [
                'id' => $enrollment->id,
                'name' => $enrollment->sequence->name,
                'status' => $enrollment->status->value,
                'nextStepAt' => $enrollment->next_step_at?->toISOString(),
                'stoppedReason' => $enrollment->stopped_reason,
            ]),
            'tasks' => $lead->tasks()->with('assignee:id,name')->latest('id')->get()->map(fn (LeadTask $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'dueOn' => $task->due_on?->toDateString(),
                'completedAt' => $task->completed_at?->toISOString(),
                'assignee' => $task->assignee?->name,
            ]),
        ]);
    }

    /**
     * The three design options shown on the lead profile.
     *
     * @return array<string, mixed>
     */
    protected function conceptsProps(Team $team, Lead $lead, UsageMeter $usage): array
    {
        return [
            'available' => $usage->allows($team, PlanFeature::AiEditing),
            'status' => ($lead->concepts_status ?? SiteGenerationStatus::Idle)->value,
            'items' => $lead->concepts()->latest('id')->limit(3)->get()->map(fn (SiteConcept $concept) => [
                'id' => $concept->id,
                'template' => $concept->template->value,
                'label' => $concept->template->label(),
                'vibe' => $concept->template->vibe()->value,
                'headline' => $concept->content['headline'] ?? '',
                'accent' => $concept->content['accent'] ?? '',
                'previewUrl' => route('sites.concepts.preview', $concept),
            ]),
        ];
    }

    /**
     * The SMS and WhatsApp state shown on the lead's messages card.
     *
     * @return array<string, mixed>
     */
    protected function messagingProps(Team $team, Lead $lead, UsageMeter $usage): array
    {
        $account = $team->messagingAccount;

        return [
            'available' => $usage->allows($team, PlanFeature::Messaging),
            'connected' => $account !== null,
            'smsReady' => filled($account?->sms_from),
            'whatsappReady' => filled($account?->whatsapp_account_id),
            'phone' => $lead->phone_e164,
            'consentAt' => $lead->messaging_consent_at?->toISOString(),
            'optedOutAt' => $lead->messaging_opted_out_at?->toISOString(),
            'hasWhatsAppChat' => $lead->messages()->where('channel', 'whatsapp')->whereNotNull('external_conversation_id')->exists(),
            'messages' => $lead->messages()->latest('id')->limit(30)->get()->reverse()->values()->map(fn (Message $message) => [
                'id' => $message->id,
                'channel' => $message->channel->value,
                'direction' => $message->direction,
                'status' => $message->status->value,
                'body' => $message->body,
                'error' => $message->error,
                'at' => ($message->sent_at ?? $message->created_at)->toISOString(),
            ]),
        ];
    }

    /**
     * Save the owner-confirmed details, and optionally build the website.
     */
    public function update(UpdateLeadProfileRequest $request, Team $currentTeam, Lead $lead, DispatchSiteGeneration $dispatchSiteGeneration): RedirectResponse
    {
        $directory = "teams/{$currentTeam->id}/leads/{$lead->id}";
        $disk = Storage::disk('public');

        $lead->fill([
            ...$request->safe()->only(['name', 'category', 'description', 'phone', 'email', 'address', 'city', 'vibe']),
            'opening_hours' => array_filter($request->validated('opening_hours') ?? [], fn (?string $hours) => filled($hours)) ?: null,
            'details_confirmed_at' => now(),
        ]);

        if ($request->hasFile('logo') || $request->boolean('remove_logo')) {
            if ($lead->logo_path) {
                $disk->delete($lead->logo_path);
            }

            $lead->logo_path = $request->file('logo')?->store($directory, 'public');
        }

        if ($request->hasFile('photos') || $request->boolean('remove_photos')) {
            $disk->delete($lead->photo_paths ?? []);

            $lead->photo_paths = array_map(
                fn (UploadedFile $photo) => $photo->store($directory, 'public'),
                $request->file('photos', []),
            ) ?: null;
        }

        if ($request->boolean('generate')) {
            $lead->site_generation_status = SiteGenerationStatus::Queued;
            $lead->site_generation_error = null;
        }

        $lead->save();

        if ($request->boolean('generate')) {
            $dispatchSiteGeneration->handle($lead);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->boolean('generate')
            ? __('Details saved. Building the website now.')
            : __('Details saved.')]);

        return to_route('leads.edit', ['current_team' => $currentTeam->slug, 'lead' => $lead->id]);
    }
}

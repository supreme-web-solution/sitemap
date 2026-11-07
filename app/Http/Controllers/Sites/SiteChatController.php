<?php

namespace App\Http\Controllers\Sites;

use App\Enums\PlanFeature;
use App\Enums\SiteSection;
use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Jobs\ApplySiteEdit;
use App\Models\Site;
use App\Models\SiteChatMessage;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use App\Services\SiteBuilder\SectionVariants;
use App\Services\SiteBuilder\SiteChatAttachment;
use App\Services\SiteBuilder\SiteEditApplier;
use App\Services\SiteBuilder\SiteEditLog;
use App\Services\SiteBuilder\SitePendingChatEdit;
use App\Services\SiteBuilder\SiteQualityChecker;
use App\Services\SiteBuilder\SiteSectionCss;
use App\Support\Queues;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SiteChatController extends Controller
{
    /**
     * Queue an AI edit from a plain-English instruction (processed on the AI queue).
     */
    public function store(Request $request, Team $currentTeam, Site $site, UsageMeter $usage): RedirectResponse
    {
        $usage->ensureFeature($currentTeam, PlanFeature::AiEditing);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:1000'],
            'section' => ['nullable', 'string', Rule::enum(SiteSection::class)],
            'redesign' => ['sometimes', 'boolean'],
            'fix' => ['nullable', 'string', 'max:40'],
            'design_target' => ['nullable', 'string', Rule::in(SiteSectionCss::keys())],
            'preview_edits' => ['sometimes', 'array', 'max:5'],
            'preview_edits.*.region' => ['required', 'string', Rule::in(SiteSectionCss::keys())],
            'preview_edits.*.selector' => ['required', 'string', 'max:220'],
            'preview_edits.*.tag' => ['required', 'string', 'max:40'],
            'preview_edits.*.text' => ['nullable', 'string', 'max:500'],
            'preview_edits.*.href' => ['nullable', 'string', 'max:500'],
            'preview_edits.*.action' => ['nullable', 'string', 'max:40'],
            'field' => ['nullable', 'string', 'max:80', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! SiteEditApplier::isEditablePath((string) $value)) {
                    $fail(__('That part of the website cannot be improved.'));
                }
            }],
            'attachment' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.SiteChatAttachment::MAX_KILOBYTES],
            'attachments' => ['sometimes', 'array', 'max:'.SiteChatAttachment::MAX_ATTACHMENTS],
            'attachments.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:'.SiteChatAttachment::MAX_KILOBYTES],
        ]);

        if (filled($data['fix'] ?? null)) {
            $fixKey = (string) $data['fix'];

            if (! SiteQualityChecker::isAiFixable($fixKey) || SiteQualityChecker::aiInstruction($fixKey) === null) {
                throw ValidationException::withMessages([
                    'fix' => __('That quality finding cannot be fixed with AI. Update the lead details or re-run the check.'),
                ]);
            }
        }

        $previewEdits = array_values($data['preview_edits'] ?? []);
        $body = trim($this->instruction($data, $site));
        $uploadedFiles = array_values($request->file('attachments') ?? []);

        if ($request->hasFile('attachment')) {
            $uploadedFiles[] = $request->file('attachment');
        }

        $uploadedFiles = array_slice($uploadedFiles, 0, SiteChatAttachment::MAX_ATTACHMENTS);
        $hasAttachment = $uploadedFiles !== [];

        if ($body === '' && ! $hasAttachment && $previewEdits === [] && blank($data['design_target'] ?? null)) {
            throw ValidationException::withMessages([
                'body' => __('Describe a change, paste a screenshot, pick a section to improve, or fix a quality finding.'),
            ]);
        }

        if ($body === '' && ($previewEdits !== [] || filled($data['design_target'] ?? null))) {
            $body = __('Improve the design of the selected area.');
        }

        if ($body === '' && $hasAttachment) {
            $body = count($uploadedFiles) > 1
                ? __('Apply the changes I marked in the attached screenshots.')
                : __('Apply the changes I marked in the attached screenshot.');
        }

        $attachmentPaths = $hasAttachment
            ? SiteChatAttachment::storeMany($uploadedFiles, $site)
            : [];

        $this->failStale($site);

        if ($site->chatMessages()->where('status', SiteChatMessage::STATUS_PENDING)->exists()) {
            throw ValidationException::withMessages([
                'body' => __('Wait for the current edit to finish.'),
            ]);
        }

        $usage->consume($currentTeam, UsageMetric::AiEdits);

        $site->chatMessages()->create([
            'user_id' => $request->user()->id,
            'role' => SiteChatMessage::ROLE_USER,
            'body' => $body,
            'attachment_path' => $attachmentPaths[0] ?? null,
            'attachment_paths' => $attachmentPaths !== [] ? $attachmentPaths : null,
            'status' => SiteChatMessage::STATUS_DONE,
        ]);

        $userMessage = $site->chatMessages()
            ->where('role', SiteChatMessage::ROLE_USER)
            ->latest('id')
            ->first();

        return $this->queueEdit(
            $site,
            $userMessage?->id,
            $data['design_target'] ?? ($previewEdits[0]['region'] ?? null),
            $previewEdits,
            consumedUsage: true,
            forceApply: filled($data['fix'] ?? null),
        );
    }

    /**
     * Re-run a failed AI edit for the same user message (does not consume another edit credit).
     */
    public function retry(Request $request, Team $currentTeam, Site $site, UsageMeter $usage): RedirectResponse
    {
        $usage->ensureFeature($currentTeam, PlanFeature::AiEditing);

        $data = $request->validate([
            'message_id' => ['required', 'integer', 'min:1'],
        ]);

        $failed = $site->chatMessages()->whereKey($data['message_id'])->firstOrFail();

        if ($failed->role !== SiteChatMessage::ROLE_ASSISTANT || $failed->status !== SiteChatMessage::STATUS_FAILED) {
            throw ValidationException::withMessages([
                'message_id' => __('Only a failed edit can be retried.'),
            ]);
        }

        $userMessageId = $failed->resolvedUserMessageId();

        if ($userMessageId === null) {
            throw ValidationException::withMessages([
                'message_id' => __('The original request for this edit could not be found.'),
            ]);
        }

        if ($failed->user_message_id === null) {
            $failed->forceFill(['user_message_id' => $userMessageId])->save();
        }

        $attempts = SiteChatMessage::attemptCountForUserMessage($site->id, $userMessageId);

        if ($attempts >= SiteChatMessage::MAX_EDIT_ATTEMPTS) {
            throw ValidationException::withMessages([
                'message_id' => __(
                    'This edit already failed :count times. Please wait a few minutes, then send a new message.',
                    ['count' => SiteChatMessage::MAX_EDIT_ATTEMPTS],
                ),
            ]);
        }

        if (! $failed->isLatestAssistantForUserMessage($userMessageId)) {
            throw ValidationException::withMessages([
                'message_id' => __('Use Try again on the most recent failure for this request.'),
            ]);
        }

        $this->failStale($site);

        if ($site->chatMessages()->where('status', SiteChatMessage::STATUS_PENDING)->exists()) {
            throw ValidationException::withMessages([
                'message_id' => __('Wait for the current edit to finish.'),
            ]);
        }

        $userMessage = $site->chatMessages()->whereKey($userMessageId)->first();
        $forceApply = $userMessage !== null
            && SiteQualityChecker::isQualityFixInstruction((string) $userMessage->body);

        return $this->queueEdit($site, $userMessageId, null, [], consumedUsage: false, forceApply: $forceApply);
    }

    /**
     * @param  list<array{region: string, selector: string, tag: string, text?: string|null, href?: string|null, action?: string|null}>  $previewEdits
     */
    protected function queueEdit(
        Site $site,
        ?int $userMessageId,
        ?string $designTarget,
        array $previewEdits,
        bool $consumedUsage,
        bool $forceApply = false,
    ): RedirectResponse {
        $assistant = $site->chatMessages()->create([
            'user_id' => auth()->id(),
            'role' => SiteChatMessage::ROLE_ASSISTANT,
            'body' => $consumedUsage
                ? __('Applying your edit…')
                : __('Trying your edit again…'),
            'status' => SiteChatMessage::STATUS_PENDING,
            'user_message_id' => $userMessageId,
        ]);

        SiteEditLog::info('edit queued', [
            'site_id' => $site->id,
            'chat_message_id' => $assistant->id,
            'queue' => Queues::AI,
            'force_apply' => $forceApply,
        ]);

        ApplySiteEdit::dispatch($assistant, $designTarget, $previewEdits, $forceApply);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $consumedUsage
                ? __('Your edit is running — watch the build log below.')
                : __('Retrying your edit — watch the build log below.'),
        ]);

        return back();
    }

    /**
     * Stop a stuck or unwanted in-flight AI edit.
     */
    public function destroy(Request $request, Team $currentTeam, Site $site, UsageMeter $usage): RedirectResponse
    {
        $usage->ensureFeature($currentTeam, PlanFeature::AiEditing);

        if (! SitePendingChatEdit::cancelForSite($site)) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('No edit is running for this site.'),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Edit cancelled. You can send a new instruction.'),
        ]);

        return back();
    }

    /**
     * Treat a stuck pending edit as failed so the chat never stays locked.
     */
    protected function failStale(Site $site): void
    {
        $stale = $site->chatMessages()
            ->where('status', SiteChatMessage::STATUS_PENDING)
            ->where('created_at', '<', now()->subMinutes(SiteChatMessage::STALE_AFTER_MINUTES))
            ->get();

        foreach ($stale as $message) {
            $message->forceFill([
                'status' => SiteChatMessage::STATUS_FAILED,
                'body' => __('The edit timed out. Try again.'),
            ])->save();

            SiteEditProgress::forget($message->id);
        }
    }

    /**
     * @param  array{body?: string|null, section?: string|null, redesign?: bool, fix?: string|null, field?: string|null}  $data
     */
    protected function instruction(array $data, Site $site): string
    {
        if (filled($data['fix'] ?? null)) {
            return SiteQualityChecker::aiInstruction((string) $data['fix']) ?? '';
        }

        if (filled($data['field'] ?? null)) {
            $path = (string) $data['field'];
            $current = Arr::get(SiteEditApplier::document($site), $path);
            $request = trim((string) ($data['body'] ?? ''));

            return __('Change only this one piece of text on the website, at the content path ":path". Its current wording is: ":current". :request Keep facts already on the site. Do not invent licenses, years in business, or contact details. Leave everything else exactly as it is.', [
                'path' => $path,
                'current' => is_scalar($current) ? Str::limit((string) $current, 400) : '',
                'request' => $request !== '' ? __('What to do: :request.', ['request' => rtrim($request, '.')]) : __('Make it clearer and more persuasive.'),
            ]);
        }
        if (filled($data['section'] ?? null)) {
            $section = SiteSection::from((string) $data['section']);
            $key = $section->value;
            $layouts = SectionVariants::promptList($key);

            if (($data['redesign'] ?? false) === true && $layouts !== '') {
                return __('Redesign the visual layout of the :key section (:section). Pick a different layout variant from: :layouts. Set variants.:key to that choice. Keep all copy and facts. Do not change the template or other sections.', [
                    'key' => $key,
                    'section' => $section->label(),
                    'layouts' => $layouts,
                ]);
            }

            $request = trim((string) ($data['body'] ?? ''));

            return __('Rewrite only the :section section of this website. You may also pick a layout variant for it from: :layouts. Keep facts already on the site. Do not invent licenses, years in business, or contact details. :request', [
                'section' => $section->label(),
                'layouts' => $layouts !== '' ? $layouts : __('the current layout'),
                'request' => $request !== '' ? __('What to do: :request.', ['request' => rtrim($request, '.')]) : '',
            ]);
        }

        return trim((string) ($data['body'] ?? ''));
    }
}

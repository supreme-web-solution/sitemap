<?php

namespace App\Jobs;

use App\Data\SiteEditTurnDecision;
use App\Enums\LeadActivityType;
use App\Exceptions\SiteEditBlockedException;
use App\Models\SiteChatMessage;
use App\Services\Ai\AiProviderErrors;
use App\Services\SiteBuilder\LeadPhotoGenerator;
use App\Services\SiteBuilder\SiteAttachedImagePlacer;
use App\Services\SiteBuilder\SiteEditAiContext;
use App\Services\SiteBuilder\SiteEditIntent;
use App\Services\SiteBuilder\SiteEditLog;
use App\Services\SiteBuilder\SiteEditor;
use App\Services\SiteBuilder\SiteEditProgress;
use App\Services\SiteBuilder\SiteEditSummary;
use App\Services\SiteBuilder\SiteEditTurnResolver;
use App\Services\SiteBuilder\SiteHeroImageRegenerator;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class ApplySiteEdit implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    public int $backoff = 15;

    /**
     * @param  list<array{region: string, selector: string, tag: string, text?: string|null, href?: string|null, action?: string|null}>  $previewEdits
     */
    public function __construct(
        public SiteChatMessage $message,
        public ?string $designTarget = null,
        public array $previewEdits = [],
    ) {
        $this->onQueue(Queues::AI);
    }

    public function uniqueId(): string
    {
        return (string) $this->message->site_id;
    }

    public function handle(
        SiteEditor $editor,
        SiteHeroImageRegenerator $heroImages,
    ): void {
        $message = $this->message->fresh();

        if ($message === null || $message->status !== SiteChatMessage::STATUS_PENDING) {
            SiteEditLog::info('apply job skipped', [
                'chat_message_id' => $this->message->id,
                'status' => $message?->status,
            ]);

            return;
        }

        SiteEditLog::info('apply job started', [
            'site_id' => $message->site_id,
            'chat_message_id' => $message->id,
            'queue' => Queues::AI,
        ]);

        $site = $message->site;
        $userMessage = $site->chatMessages()
            ->where('role', SiteChatMessage::ROLE_USER)
            ->where('id', '<', $message->id)
            ->latest('id')
            ->first();

        $instruction = trim((string) ($userMessage?->body ?? ''));
        $attachmentPaths = $userMessage?->attachmentPaths() ?? [];

        if ($instruction === '' && $attachmentPaths === []) {
            $this->markFailed($message, __('The edit request was lost. Try again.'));

            return;
        }

        if ($instruction === '' && $attachmentPaths !== []) {
            $instruction = count($attachmentPaths) > 1
                ? __('Apply the changes shown in the attached screenshots.')
                : __('Apply the changes shown in the attached screenshot.');
        }

        $beforeContent = $site->content;

        SiteEditLog::info('apply job', [
            'site_id' => $site->id,
            'chat_message_id' => $message->id,
            'instruction' => $instruction,
            'has_attachment' => $attachmentPaths !== [],
            'attachment_count' => count($attachmentPaths),
        ]);

        $progress = new SiteEditProgress($message->id);
        $progress->line(__('Reading your request'));

        SiteEditAiContext::reset();

        SiteEditLog::info('turn resolve starting', [
            'site_id' => $site->id,
            'chat_message_id' => $message->id,
            'instruction' => Str::limit($instruction, 300),
        ]);

        $started = microtime(true);
        $turn = app(SiteEditTurnResolver::class)->resolve($site, $instruction, $attachmentPaths);

        SiteEditLog::info('turn resolve finished', [
            'site_id' => $site->id,
            'chat_message_id' => $message->id,
            'seconds' => round(microtime(true) - $started, 2),
            'reply_only' => $turn->isReplyOnly(),
        ]);

        if ($turn->isReplyOnly() && SiteEditAiContext::hadQuotaFailure()) {
            $this->markFailed($message, $turn->message !== ''
                ? $turn->message
                : AiProviderErrors::siteEditUnavailableMessage());

            return;
        }

        if ($turn->isReplyOnly()) {
            SiteEditLog::info('apply job conversational reply', [
                'site_id' => $site->id,
                'from_llm' => $turn->fromLlm,
            ]);

            $message->forceFill([
                'status' => SiteChatMessage::STATUS_DONE,
                'body' => $turn->message,
            ])->save();

            SiteEditProgress::forget($message->id);

            return;
        }

        $progress->line(__('Saved a backup of the current site'));

        $version = null;
        $imageNote = '';

        if ($turn->shouldApplyAttachedImage()) {
            if ($attachmentPaths === []) {
                $imageNote = __('No image was attached to this message.');
            } else {
                $progress->line(__('Adding your photo to the site'));

                try {
                    $notes = [];

                    foreach ($attachmentPaths as $index => $path) {
                        $placement = $index === 0
                            ? $turn->attachedImagePlacement
                            : SiteAttachedImagePlacer::PLACEMENT_GALLERY;

                        $notes[] = app(SiteAttachedImagePlacer::class)->place(
                            $site,
                            $path,
                            $placement,
                        );
                    }

                    $imageNote = implode(' ', array_unique($notes));
                } catch (\RuntimeException $exception) {
                    if (! $turn->shouldApplyEdit()) {
                        $message->forceFill([
                            'status' => SiteChatMessage::STATUS_DONE,
                            'body' => $exception->getMessage(),
                        ])->save();

                        SiteEditProgress::forget($message->id);

                        return;
                    }

                    $imageNote = $exception->getMessage();
                }
            }
        }

        if (
            ($turn->shouldGenerateHeroImage() || SiteEditIntent::wantsHeroPhotoBackground($instruction))
            && ! $turn->shouldApplyAttachedImage()
        ) {
            $progress->line(__('Generating a hero photo'));

            try {
                $imageNote = $heroImages->regenerate($site, $turn->imageHint, $attachmentPaths);
            } catch (Throwable $exception) {
                $imageNote = $exception instanceof \RuntimeException
                    ? $exception->getMessage()
                    : LeadPhotoGenerator::unavailableMessage();

                SiteEditLog::info('hero image generation skipped', [
                    'site_id' => $site->id,
                    'reason' => Str::limit($imageNote, 200),
                ]);

                if (! $turn->shouldApplyEdit()) {
                    $body = trim($turn->message);

                    if ($body !== '') {
                        $body = trim($body."\n\n".$imageNote);
                    } else {
                        $body = $imageNote;
                    }

                    $message->forceFill([
                        'status' => SiteChatMessage::STATUS_DONE,
                        'body' => $body,
                    ])->save();

                    SiteEditProgress::forget($message->id);

                    return;
                }
            }
        }

        $body = trim($turn->message);

        if ($turn->shouldApplyEdit()) {
            $applyInstruction = $instruction;

            try {
                $draft = $editor->apply(
                    $site,
                    $applyInstruction,
                    $this->designTarget,
                    $this->previewEdits,
                    $progress,
                    $attachmentPaths,
                    $instruction,
                );
            } catch (SiteEditBlockedException $exception) {
                $this->markFailed($message, $exception->getMessage());

                return;
            } catch (\RuntimeException $exception) {
                $this->markFailed($message, $exception->getMessage());

                return;
            }

            if (! $this->stillPending($message)) {
                SiteEditProgress::forget($message->id);

                return;
            }

            $version = $site->snapshot($message->user_id, __('Before AI edit'));

            $site->update([
                'template' => $draft->template,
                'content' => $draft->content,
            ]);

            $summary = SiteEditSummary::describe(
                $beforeContent,
                $draft->content,
                $applyInstruction,
                aiQuotaFailure: SiteEditAiContext::hadQuotaFailure(),
            );

            if (
                SiteEditAiContext::hadQuotaFailure()
                && str_contains($summary, 'Nothing changed on the site')
            ) {
                $this->markFailed($message, AiProviderErrors::siteEditPartialUnavailableMessage());

                return;
            }

            $body = $this->assistantBody($turn, $summary, $imageNote);
        } elseif ($imageNote !== '') {
            $body = trim($body."\n\n".$imageNote);
        }

        if ($body === '') {
            $body = $imageNote !== '' ? $imageNote : __('Done.');
        }

        if (isset($progress)) {
            $progress->line(__('Refreshing the preview'));
        }

        if (! $this->stillPending($message)) {
            SiteEditProgress::forget($message->id);

            return;
        }

        SiteEditLog::info('apply job done', [
            'site_id' => $site->id,
            'summary' => Str::limit($body, 300),
        ]);

        $message->forceFill([
            'status' => SiteChatMessage::STATUS_DONE,
            'body' => $body,
            'site_version_id' => $version?->id,
        ])->save();

        if ($turn->shouldApplyEdit() || $turn->shouldGenerateHeroImage() || $turn->shouldApplyAttachedImage()) {
            $site->lead->recordActivity(
                LeadActivityType::SiteEdited,
                __('AI edit: :change', ['change' => Str::limit($instruction, 120)]),
                ['site_id' => $site->id, 'chat_message_id' => $message->id],
                $message->user_id,
            );
        }

        SiteEditProgress::forget($message->id);
    }

    public function failed(?Throwable $exception): void
    {
        SiteEditLog::info('apply job failed', [
            'chat_message_id' => $this->message->id,
            'exception' => $exception === null ? null : $exception::class,
            'message' => $exception === null ? null : Str::limit($exception->getMessage(), 500),
        ]);

        $message = $this->message->fresh();

        if ($message !== null && $message->status === SiteChatMessage::STATUS_PENDING) {
            $this->markFailed($message, __('The website could not be edited. Please try again.'));
        }
    }

    protected function assistantBody(SiteEditTurnDecision $turn, string $summary, string $imageNote): string
    {
        $message = trim($turn->message);

        if ($imageNote !== '' && ! str_contains($message, $imageNote)) {
            $message = trim($message."\n\n".$imageNote);
        }

        if ($message === '') {
            return $summary;
        }

        if (str_contains($summary, 'Here is what changed')) {
            return $message."\n\n".$summary;
        }

        if (str_starts_with($summary, 'Nothing changed') || str_contains($summary, 'already on the homepage')) {
            return $message;
        }

        return $message."\n\n".$summary;
    }

    protected function stillPending(SiteChatMessage $message): bool
    {
        $fresh = $message->fresh();

        return $fresh !== null && $fresh->status === SiteChatMessage::STATUS_PENDING;
    }

    protected function markFailed(SiteChatMessage $message, string $reason): void
    {
        if (! $this->stillPending($message)) {
            SiteEditProgress::forget($message->id);

            return;
        }

        SiteEditLog::info('apply job marked failed', [
            'chat_message_id' => $message->id,
            'reason' => Str::limit($reason, 300),
        ]);

        $message->forceFill([
            'status' => SiteChatMessage::STATUS_FAILED,
            'body' => $reason,
        ])->save();

        SiteEditProgress::forget($message->id);
    }
}

<?php

namespace App\Jobs;

use App\Enums\LeadActivityType;
use App\Models\SiteChatMessage;
use App\Services\SiteBuilder\SiteEditor;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class ApplySiteEdit implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * Must stay below the queue connection's retry_after.
     */
    public int $timeout = 80;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * Seconds to wait before retrying after an exception.
     */
    public int $backoff = 15;

    public function __construct(public SiteChatMessage $message)
    {
        $this->onQueue(Queues::AI);
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->message->site_id;
    }

    /**
     * Apply the user's instruction to the live website and snapshot the previous version.
     */
    public function handle(SiteEditor $editor): void
    {
        $message = $this->message->fresh();

        if ($message === null || $message->status !== SiteChatMessage::STATUS_PENDING) {
            return;
        }

        $site = $message->site;
        $instruction = $site->chatMessages()
            ->where('role', SiteChatMessage::ROLE_USER)
            ->where('id', '<', $message->id)
            ->latest('id')
            ->value('body');

        if (! is_string($instruction) || $instruction === '') {
            $this->markFailed($message, __('The edit request was lost. Try again.'));

            return;
        }

        $version = $site->snapshot($message->user_id, __('Before AI edit'));
        $draft = $editor->apply($site, $instruction);

        $site->update([
            'template' => $draft->template,
            'content' => $draft->content,
        ]);

        $message->forceFill([
            'status' => SiteChatMessage::STATUS_DONE,
            'body' => __('Updated the website: :change', ['change' => Str::limit($instruction, 120)]),
            'site_version_id' => $version->id,
        ])->save();

        $site->lead->recordActivity(
            LeadActivityType::SiteEdited,
            __('AI edit: :change', ['change' => Str::limit($instruction, 120)]),
            ['site_id' => $site->id, 'chat_message_id' => $message->id],
            $message->user_id,
        );
    }

    /**
     * Mark the chat line failed once every attempt is used up.
     */
    public function failed(?Throwable $exception): void
    {
        $message = $this->message->fresh();

        if ($message !== null && $message->status === SiteChatMessage::STATUS_PENDING) {
            $this->markFailed($message, __('The website could not be edited. Please try again.'));
        }
    }

    protected function markFailed(SiteChatMessage $message, string $reason): void
    {
        $message->forceFill([
            'status' => SiteChatMessage::STATUS_FAILED,
            'body' => $reason,
        ])->save();
    }
}

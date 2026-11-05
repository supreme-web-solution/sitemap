<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PreviewCommentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Lead $lead,
        public string $authorName,
        public ?string $authorContact,
        public ?string $section,
        public string $body,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('Comment on the :business website', ['business' => $this->lead->name]))
            ->line(__(':who left a comment on the website preview.', ['who' => $this->authorName]));

        if ($this->section) {
            $message->line(__('Section: :section', ['section' => $this->section]));
        }

        if ($this->authorContact) {
            $message->line(__('Contact: :contact', ['contact' => $this->authorContact]));
        }

        $message->line($this->body);

        return $message->action(__('Open the lead'), route('leads.edit', [
            'current_team' => $this->lead->team->slug,
            'lead' => $this->lead->id,
        ]));
    }
}

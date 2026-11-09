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
        $this->lead->loadMissing('team');

        $details = array_filter([
            __('From') => $this->authorName,
            __('Section') => $this->section,
            __('Contact') => $this->authorContact,
        ], fn (?string $value) => filled($value));

        $mail = (new MailMessage)
            ->subject(__('Comment on the :business website', ['business' => $this->lead->name]))
            ->view(['mail.lead-alert', 'mail.lead-alert-text'], [
                'brand' => $this->lead->team->brand(),
                'title' => __('New preview comment'),
                'intro' => __(':who left a comment on the website preview.', ['who' => $this->authorName]),
                'details' => $details,
                'messageBody' => $this->body,
                'url' => route('leads.edit', [
                    'current_team' => $this->lead->team->slug,
                    'lead' => $this->lead->id,
                ]),
                'cta' => __('Open the lead'),
            ]);

        if (filled($this->authorContact) && str_contains($this->authorContact, '@')) {
            $mail->replyTo($this->authorContact, $this->authorName);
        }

        return $mail;
    }
}

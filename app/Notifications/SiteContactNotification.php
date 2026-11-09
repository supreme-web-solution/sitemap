<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SiteContactNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Lead $lead,
        public string $name,
        public string $contact,
        public string $message,
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

        $mail = (new MailMessage)
            ->subject(__('Message from the :business website', ['business' => $this->lead->name]))
            ->view(['mail.lead-alert', 'mail.lead-alert-text'], [
                'brand' => $this->lead->team->brand(),
                'title' => __('New website message'),
                'intro' => __(':who sent a message from the :business website.', [
                    'who' => $this->name,
                    'business' => $this->lead->name,
                ]),
                'details' => [
                    __('From') => $this->name,
                    __('Reply to') => $this->contact,
                ],
                'messageBody' => $this->message,
                'url' => route('leads.edit', [
                    'current_team' => $this->lead->team->slug,
                    'lead' => $this->lead,
                ]),
                'cta' => __('Open the lead'),
            ]);

        if (str_contains($this->contact, '@')) {
            $mail->replyTo($this->contact, $this->name);
        }

        return $mail;
    }
}

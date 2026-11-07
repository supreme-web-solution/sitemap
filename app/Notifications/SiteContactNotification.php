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
            ->greeting(__('New website message'))
            ->line(__(':who sent a message from the :business website.', [
                'who' => $this->name,
                'business' => $this->lead->name,
            ]))
            ->line(__('From: :name', ['name' => $this->name]))
            ->line(__('Reply to: :contact', ['contact' => $this->contact]))
            ->line(__('Message:'))
            ->line($this->message)
            ->action(__('Open the lead'), route('leads.edit', [
                'current_team' => $this->lead->team->slug,
                'lead' => $this->lead,
            ]));

        if (str_contains($this->contact, '@')) {
            $mail->replyTo($this->contact, $this->name);
        }

        return $mail;
    }
}

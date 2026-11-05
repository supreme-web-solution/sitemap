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
        return (new MailMessage)
            ->subject(__('Message from the :business website', ['business' => $this->lead->name]))
            ->line(__(':who sent a message from the website.', ['who' => $this->name]))
            ->line($this->contact)
            ->line($this->message)
            ->action(__('Open the lead'), route('leads.edit', $this->lead));
    }
}

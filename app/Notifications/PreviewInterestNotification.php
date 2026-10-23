<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PreviewInterestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Lead $lead, public ?string $visitorName, public ?string $visitorContact, public ?string $visitorMessage) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__(':business is interested in their website', ['business' => $this->lead->name]))
            ->line(__(':business replied from their website preview.', ['business' => $this->lead->name]));

        if ($this->visitorName) {
            $message->line(__('Name: :name', ['name' => $this->visitorName]));
        }

        if ($this->visitorContact) {
            $message->line(__('Contact: :contact', ['contact' => $this->visitorContact]));
        }

        if ($this->visitorMessage) {
            $message->line(__('Message: :message', ['message' => $this->visitorMessage]));
        }

        return $message->action(__('Open the lead'), route('leads.edit', [
            'current_team' => $this->lead->team->slug,
            'lead' => $this->lead->id,
        ]));
    }
}

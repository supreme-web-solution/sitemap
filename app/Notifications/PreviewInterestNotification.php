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
    public function __construct(
        public Lead $lead,
        public ?string $visitorName,
        public ?string $visitorContact,
        public ?string $visitorMessage,
        public string $intent = 'discuss',
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

        $isClaim = $this->intent === 'claim';
        $details = array_filter([
            __('Name') => $this->visitorName,
            __('Contact') => $this->visitorContact,
        ], fn (?string $value) => filled($value));

        $mail = (new MailMessage)
            ->subject($isClaim
                ? __(':business wants to claim their website', ['business' => $this->lead->name])
                : __(':business is interested in their website', ['business' => $this->lead->name]))
            ->view(['mail.lead-alert', 'mail.lead-alert-text'], [
                'brand' => $this->lead->team->brand(),
                'title' => $isClaim ? __('New claim request') : __('New preview reply'),
                'intro' => $isClaim
                    ? __(':business submitted a claim request from their website preview.', ['business' => $this->lead->name])
                    : __(':business replied from their website preview.', ['business' => $this->lead->name]),
                'details' => $details,
                'messageBody' => $this->visitorMessage,
                'url' => route('leads.edit', [
                    'current_team' => $this->lead->team->slug,
                    'lead' => $this->lead->id,
                ]),
                'cta' => __('Open the lead'),
            ]);

        if (filled($this->visitorContact) && str_contains($this->visitorContact, '@')) {
            $mail->replyTo($this->visitorContact, $this->visitorName ?: null);
        }

        return $mail;
    }
}

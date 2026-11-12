<?php

namespace App\Notifications;

use App\Enums\UsageMetric;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanLimitReachedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Team $team,
        public UsageMetric $metric,
        public int $limit,
        public int $used,
        public ?int $blockedCount = null,
        public ?string $context = null,
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
        $details = array_filter([
            __('Plan metric') => $this->metric->label(),
            __('Usage') => __(':used of :limit', [
                'used' => number_format($this->used),
                'limit' => number_format($this->limit),
            ]),
            __('Blocked this time') => $this->blockedCount !== null && $this->blockedCount > 0
                ? number_format($this->blockedCount)
                : null,
            __('What happened') => $this->context,
        ], fn (?string $value) => filled($value));

        return (new MailMessage)
            ->subject(__('Plan limit reached for :team', ['team' => $this->team->name]))
            ->view(['mail.lead-alert', 'mail.lead-alert-text'], [
                'brand' => $this->team->brand(),
                'title' => __('Plan limit reached'),
                'intro' => __('Your team hit the :metric limit on its plan. Upgrade the plan or free up space to continue.', [
                    'metric' => strtolower($this->metric->label()),
                ]),
                'details' => $details,
                'messageBody' => null,
                'url' => route('plan.index', ['current_team' => $this->team->slug]),
                'cta' => __('View plan & usage'),
                'footer' => __('Ask your administrator to raise the plan limit, or delete items you no longer need.'),
            ]);
    }
}

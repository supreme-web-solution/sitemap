<?php

namespace App\Jobs;

use App\Models\Membership;
use App\Services\CommandCenter\CommandCenterWhatsAppResponder;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class PulseCommandCenterWhatsAppTyping implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $membershipId)
    {
        $this->onQueue(Queues::WEBHOOKS);
    }

    public function handle(CommandCenterWhatsAppResponder $whatsapp): void
    {
        $membership = Membership::query()->find($this->membershipId);

        if ($membership === null || $membership->command_center_processing_at === null) {
            Cache::forget($this->cacheKey());

            return;
        }

        $whatsapp->sendTypingIndicator($membership);

        self::dispatch($this->membershipId)->delay(now()->addSeconds(8));
    }

    protected function cacheKey(): string
    {
        return "command-center-wa-typing:{$this->membershipId}";
    }
}

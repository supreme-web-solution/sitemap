<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiCreditEvent;
use Inertia\Inertia;
use Inertia\Response;

class AdminAiCreditsController extends Controller
{
    /**
     * List AI credit and quota failures, including when the job finished.
     */
    public function __invoke(): Response
    {
        $events = AiCreditEvent::query()
            ->with(['lead:id,name', 'team:id,name'])
            ->latest('occurred_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AiCreditEvent $event): array => [
                'id' => $event->id,
                'provider' => $event->providerLabel(),
                'source' => $event->sourceLabel(),
                'message' => $event->message,
                'outcome' => $event->outcome,
                'leadName' => $event->lead?->name,
                'teamName' => $event->team?->name,
                'occurredAt' => $event->occurred_at->toIso8601String(),
                'finishedAt' => $event->finished_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/AiCredits', [
            'events' => $events,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Sites;

use App\Enums\PlanFeature;
use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Jobs\ApplySiteEdit;
use App\Models\Site;
use App\Models\SiteChatMessage;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SiteChatController extends Controller
{
    /**
     * Queue an AI edit of the website from a plain-English instruction.
     */
    public function store(Request $request, Team $currentTeam, Site $site, UsageMeter $usage): RedirectResponse
    {
        $usage->ensureFeature($currentTeam, PlanFeature::AiEditing);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $this->failStale($site);

        if ($site->chatMessages()->where('status', SiteChatMessage::STATUS_PENDING)->exists()) {
            throw ValidationException::withMessages([
                'body' => __('Wait for the current edit to finish.'),
            ]);
        }

        $usage->consume($currentTeam, UsageMetric::AiEdits);

        $site->chatMessages()->create([
            'user_id' => $request->user()->id,
            'role' => SiteChatMessage::ROLE_USER,
            'body' => $data['body'],
            'status' => SiteChatMessage::STATUS_DONE,
        ]);

        $assistant = $site->chatMessages()->create([
            'user_id' => $request->user()->id,
            'role' => SiteChatMessage::ROLE_ASSISTANT,
            'body' => __('Editing the website…'),
            'status' => SiteChatMessage::STATUS_PENDING,
        ]);

        ApplySiteEdit::dispatch($assistant);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Editing the website.')]);

        return back();
    }

    /**
     * Treat a stuck pending edit as failed so the chat never stays locked.
     */
    protected function failStale(Site $site): void
    {
        $site->chatMessages()
            ->where('status', SiteChatMessage::STATUS_PENDING)
            ->where('created_at', '<', now()->subMinutes(SiteChatMessage::STALE_AFTER_MINUTES))
            ->update([
                'status' => SiteChatMessage::STATUS_FAILED,
                'body' => __('The edit timed out. Try again.'),
            ]);
    }
}

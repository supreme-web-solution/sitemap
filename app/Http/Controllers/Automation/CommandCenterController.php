<?php

namespace App\Http\Controllers\Automation;

use App\Enums\CommandCenterMessageSource;
use App\Enums\CommandCenterMode;
use App\Enums\CommandCenterPendingActionStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessCommandCenterMessage;
use App\Models\CommandCenterPendingAction;
use App\Models\Team;
use App\Models\User;
use App\Services\Ai\AiAvailability;
use App\Services\CommandCenter\CommandCenterActionExecutor;
use App\Services\CommandCenter\CommandCenterChatLog;
use App\Services\CommandCenter\CommandCenterSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CommandCenterController extends Controller
{
    public function index(Team $currentTeam, CommandCenterSession $session, Request $request): Response
    {
        $user = $request->user();
        $mode = $session->mode($currentTeam, $user);
        $employeeName = config('command_center.employee_name');

        $page = $session->messagePage($currentTeam, $user);
        $messages = $page['messages'];
        $hasOlderMessages = $page['hasOlder'];

        if ($messages === []) {
            $messages = [[
                'id' => 'welcome',
                'role' => 'assistant',
                'body' => __('Good morning! I\'m :name — I can find leads, move your pipeline, run playbooks, and queue site work. What should we do first?', [
                    'name' => $employeeName,
                ]),
                'at' => now()->toISOString(),
                'source' => 'web',
            ]];
            $hasOlderMessages = false;
        }

        return Inertia::render('command-center/Index', [
            'employee' => [
                'name' => $employeeName,
                'title' => config('command_center.employee_title'),
                'avatarUrl' => config('command_center.employee_avatar_url'),
            ],
            'messages' => $messages,
            'hasOlderMessages' => $hasOlderMessages,
            'chatProcessing' => $session->isProcessing($currentTeam, $user),
            'whatsapp' => $session->whatsAppSetup($currentTeam, $user),
            'mode' => $mode->value,
            'modeOptions' => CommandCenterMode::options(),
            'aiAvailable' => AiAvailability::openaiConfigured(),
            'pendingActions' => $this->pendingActions($currentTeam, $user),
            'quickPrompts' => [
                __('Find plumbers without websites in Austin'),
                __('How does my pipeline look?'),
                __('Show my newest leads'),
                __('What background automations are on?'),
            ],
            'recentRuns' => $currentTeam->automationRuns()
                ->with('lead:id,name')
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn ($run) => [
                    'triggerLabel' => $run->triggerEnum()->label(),
                    'summary' => $run->summary,
                    'leadName' => $run->lead?->name,
                    'createdAt' => $run->created_at?->toISOString(),
                ]),
        ]);
    }

    public function updateMode(Request $request, Team $currentTeam, CommandCenterSession $session): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::enum(CommandCenterMode::class)],
        ]);

        $session->updateMode(
            $currentTeam,
            $request->user(),
            CommandCenterMode::from($data['mode']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Command Center mode updated.')]);

        return back();
    }

    public function history(Request $request, Team $currentTeam, CommandCenterSession $session): JsonResponse
    {
        $data = $request->validate([
            'before' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(
            $session->messagePage($currentTeam, $request->user(), (int) $data['before']),
        );
    }

    public function store(
        Request $request,
        Team $currentTeam,
        CommandCenterSession $session,
        CommandCenterChatLog $chatLog,
    ): RedirectResponse {
        if (! AiAvailability::openaiConfigured()) {
            throw ValidationException::withMessages([
                'message' => __('Add OPENAI_API_KEY in your environment to chat with the Command Center.'),
            ]);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $user = $request->user();
        $membership = $session->membership($currentTeam, $user);

        if ($membership->command_center_processing_at !== null) {
            throw ValidationException::withMessages([
                'message' => __(':name is still working on your last message.', [
                    'name' => config('command_center.employee_name'),
                ]),
            ]);
        }

        $chatLog->appendUser($currentTeam, $user, $data['message'], CommandCenterMessageSource::Web);

        $membership->forceFill(['command_center_processing_at' => now()])->save();

        ProcessCommandCenterMessage::dispatch(
            $currentTeam->id,
            $user->id,
            $data['message'],
            'web',
        );

        return back();
    }

    public function approvePending(
        Request $request,
        Team $currentTeam,
        CommandCenterPendingAction $pendingAction,
        CommandCenterActionExecutor $executor,
    ): RedirectResponse {
        $this->authorizePending($currentTeam, $request->user()->id, $pendingAction);

        $result = $executor->run(
            $pendingAction->action,
            $pendingAction->payload,
            $currentTeam,
            $request->user(),
        );

        $pendingAction->forceFill([
            'status' => CommandCenterPendingActionStatus::Approved,
            'resolved_at' => now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => $result]);

        return back();
    }

    public function dismissPending(
        Request $request,
        Team $currentTeam,
        CommandCenterPendingAction $pendingAction,
    ): RedirectResponse {
        $this->authorizePending($currentTeam, $request->user()->id, $pendingAction);

        $pendingAction->forceFill([
            'status' => CommandCenterPendingActionStatus::Dismissed,
            'resolved_at' => now(),
        ])->save();

        return back();
    }

    public function destroy(Request $request, Team $currentTeam, CommandCenterSession $session): RedirectResponse
    {
        $session->clearConversation($currentTeam, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Started a fresh conversation.')]);

        return back();
    }

    /**
     * @return list<array{id: int, summary: string, action: string, createdAt: string|null}>
     */
    protected function pendingActions(Team $team, User $user): array
    {
        return CommandCenterPendingAction::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('status', CommandCenterPendingActionStatus::Pending)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (CommandCenterPendingAction $row) => [
                'id' => $row->id,
                'summary' => $row->summary,
                'action' => $row->action,
                'createdAt' => $row->created_at?->toISOString(),
            ])
            ->all();
    }

    protected function authorizePending(Team $team, int $userId, CommandCenterPendingAction $pendingAction): void
    {
        if ($pendingAction->team_id !== $team->id
            || $pendingAction->user_id !== $userId
            || $pendingAction->status !== CommandCenterPendingActionStatus::Pending) {
            abort(404);
        }
    }
}

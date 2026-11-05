<?php

namespace App\Services\CommandCenter;

use App\Enums\CommandCenterMessageSource;
use App\Models\CommandCenterMessage;
use App\Models\Team;
use App\Models\User;

class CommandCenterChatLog
{
    public const PAGE_SIZE = 25;

    public function appendUser(Team $team, User $user, string $body, CommandCenterMessageSource $source): void
    {
        $this->append($team, $user, 'user', $body, $source);
    }

    public function appendAssistant(Team $team, User $user, string $body, CommandCenterMessageSource $source): void
    {
        $this->append($team, $user, 'assistant', $body, $source);
    }

    public function append(
        Team $team,
        User $user,
        string $role,
        string $body,
        CommandCenterMessageSource $source,
    ): void {
        $body = trim($body);

        if ($body === '') {
            return;
        }

        CommandCenterMessage::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => $role,
            'source' => $source,
            'body' => $body,
        ]);
    }

    public function clear(Team $team, User $user): void
    {
        CommandCenterMessage::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * Latest page, or the page of messages older than $beforeId.
     *
     * @return array{messages: list<array{id: string, role: string, body: string, source: string, at: string|null}>, hasOlder: bool}
     */
    public function pageForUi(Team $team, User $user, ?int $beforeId = null, int $limit = self::PAGE_SIZE): array
    {
        $query = CommandCenterMessage::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id);

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        $rows = $query->orderByDesc('id')->limit($limit + 1)->get();
        $hasOlder = $rows->count() > $limit;

        $messages = $rows
            ->take($limit)
            ->sortBy('id')
            ->values()
            ->map(fn (CommandCenterMessage $message) => [
                'id' => (string) $message->id,
                'role' => $message->role,
                'body' => $message->body,
                'source' => $message->source->value,
                'at' => $message->created_at?->toISOString(),
            ])
            ->all();

        return [
            'messages' => $messages,
            'hasOlder' => $hasOlder,
        ];
    }
}

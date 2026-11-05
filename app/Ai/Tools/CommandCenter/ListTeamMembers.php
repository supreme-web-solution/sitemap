<?php

namespace App\Ai\Tools\CommandCenter;

use App\Support\CommandCenter\CommandCenterContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListTeamMembers implements Tool
{
    public function __construct(protected CommandCenterContext $context) {}

    public function description(): Stringable|string
    {
        return 'List team members (for assigning leads).';
    }

    public function handle(Request $request): Stringable|string
    {
        $members = $this->context->team->members()
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email']);

        if ($members->isEmpty()) {
            return 'No team members found.';
        }

        $lines = $members->map(fn ($user) => "#{$user->id} {$user->name} ({$user->email})");

        return "Team members:\n".$lines->implode("\n");
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

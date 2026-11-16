<?php

namespace App\Http\Middleware;

use App\Services\Auth\UserRoleAssigner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function __construct(
        private readonly UserRoleAssigner $roles,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_unless($user !== null && in_array($permission, $this->roles->permissionsFor($user), true), 403);

        return $next($request);
    }
}

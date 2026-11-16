<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

final class UserRoleAssigner
{
    public const DEFAULT_ROLE = 'FE';

    /** @var list<string> */
    public const ASSIGNABLE_ROLES = [
        'FE',
        'Bundle',
        'Reseller',
        'AgencyVault',
        'Online Reputation App',
        'Ai Multichannel Sales Employee',
    ];

    public function rolesEnabled(): bool
    {
        if (! Schema::hasTable('roles')) {
            return false;
        }

        try {
            return Role::query()->where('name', self::DEFAULT_ROLE)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    public function permissionsFor(User $user): array
    {
        if (! $this->rolesEnabled()) {
            return ['view_app_features'];
        }

        if ($user->roles()->count() === 0) {
            return ['view_app_features'];
        }

        $names = [];

        foreach ($user->getAllPermissions() as $permission) {
            $names[] = $permission->name;
        }

        return $names;
    }

    /**
     * @param  list<string>  $roles
     */
    public function syncRoles(User $user, array $roles): void
    {
        if (! $this->rolesEnabled()) {
            return;
        }

        $assigned = [];

        foreach ($roles as $role) {
            if ($role !== '') {
                $assigned[$role] = $role;
            }
        }

        $user->syncRoles(array_values($assigned));
    }

    /**
     * @return list<string>
     */
    public function assignableRoles(): array
    {
        if (! $this->rolesEnabled()) {
            return [];
        }

        $roles = [];

        foreach (self::ASSIGNABLE_ROLES as $name) {
            if (Role::query()->where('name', $name)->exists()) {
                $roles[] = $name;
            }
        }

        return $roles;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(): array
    {
        if (! $this->rolesEnabled()) {
            return [];
        }

        return [
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::in($this->assignableRoles())],
        ];
    }
}

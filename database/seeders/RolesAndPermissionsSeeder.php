<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Role name => permissions. Role names must match the `funnel` column in ProductTableSeeder.
     *
     * FE is the core app only. Bundle (full bundle and fast-pass) unlocks every menu.
     * Reseller and each upsell add that page on top of the core app. They do not replace it.
     * Purchases stack on the same account.
     *
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = [
        'FE' => [
            'view_app_features',
        ],
        'Bundle' => [
            'view_app_features',
            'access_reseller',
            'access_agency_vault',
            'access_online_reputation',
            'access_ai_sales_employee',
        ],
        'Reseller' => [
            'view_app_features',
            'access_reseller',
        ],
        'AgencyVault' => [
            'view_app_features',
            'access_agency_vault',
        ],
        'Online Reputation App' => [
            'view_app_features',
            'access_online_reputation',
        ],
        'Ai Multichannel Sales Employee' => [
            'view_app_features',
            'access_ai_sales_employee',
        ],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionNames = collect(self::ROLE_PERMISSIONS)->flatten()->unique()->values();

        Permission::query()
            ->whereNotIn('name', $permissionNames)
            ->get()
            ->each(fn (Permission $permission) => $permission->delete());

        foreach ($permissionNames as $permissionName) {
            Permission::query()->firstOrCreate(['name' => $permissionName]);
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            Role::query()->firstOrCreate(['name' => $roleName])->syncPermissions($permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Safe to run in production (idempotent): php artisan db:seed --class=JvzooAccessSeeder --force
 */
class JvzooAccessSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            ProductTableSeeder::class,
        ]);
    }
}

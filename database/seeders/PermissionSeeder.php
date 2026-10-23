<?php

namespace Database\Seeders;

use App\Services\Permissions\PermissionSynchronizer;
use Illuminate\Database\Seeder;

/**
 * Syncs the permissions table with App\Enums\Permission. Safe to run on every deploy:
 * `php artisan db:seed --class=PermissionSeeder --force`.
 */
class PermissionSeeder extends Seeder
{
    public function run(PermissionSynchronizer $permissions): void
    {
        $permissions->sync();
    }
}

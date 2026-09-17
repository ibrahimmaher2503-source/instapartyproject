<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class NotificationProviderSettingsPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'manage_notification_providers', 'guard_name' => 'web']);
        Role::query()->where('name', 'super_admin')->where('guard_name', 'web')->first()?->givePermissionTo($permission);
    }
}

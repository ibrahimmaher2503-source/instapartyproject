<?php

declare(strict_types=1);

namespace App\Modules\Shared\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class OperationalPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['export_operational_records', 'view_operational_analytics', 'manage_internal_contact_history'] as $name) {
            $permission = Permission::findOrCreate($name, 'web');
            Role::where('name', 'super_admin')->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

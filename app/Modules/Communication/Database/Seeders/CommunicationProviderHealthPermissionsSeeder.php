<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CommunicationProviderHealthPermissionsSeeder extends Seeder
{
    private const PERMISSIONS = [
        'view_communication_provider_health',
        'send_test_notification',
        'retry_notification_dispatch',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        try {
            $adminRole = Role::findByName('admin', 'web');
            $adminRole->givePermissionTo(self::PERMISSIONS);
        } catch (RoleDoesNotExist) {
            // admin role doesn't exist yet — skip gracefully
        }

        try {
            $superAdmin = Role::findByName('super_admin', 'web');
            $superAdmin->givePermissionTo(self::PERMISSIONS);
        } catch (RoleDoesNotExist) {
            // super_admin role doesn't exist yet — skip gracefully
        }
    }
}

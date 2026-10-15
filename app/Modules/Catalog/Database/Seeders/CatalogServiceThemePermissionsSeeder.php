<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class CatalogServiceThemePermissionsSeeder extends Seeder
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'view_any_service::theme',
        'view_service::theme',
        'create_service::theme',
        'update_service::theme',
        'delete_service::theme',
        'delete_any_service::theme',
        'force_delete_service::theme',
        'force_delete_any_service::theme',
        'restore_service::theme',
        'restore_any_service::theme',
        'replicate_service::theme',
        'reorder_service::theme',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(self::PERMISSIONS)
            ->map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'));

        Role::findOrCreate('admin', 'web')->givePermissionTo($permissions);
        Role::findOrCreate('super_admin', 'web')->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

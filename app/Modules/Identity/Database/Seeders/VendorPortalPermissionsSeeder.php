<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class VendorPortalPermissionsSeeder extends Seeder
{
    private const VENDOR_ROLE_PERMISSIONS = [
        'booking.respond.own',
        'wallet.withdraw.own',
        'settlement.view_wallet.own',
        'settlement.request_withdrawal.own',
        'settlement.view_withdrawals.own',
        'update_booking_fulfillment.own',
    ];

    private const VENDOR_FILAMENT_RESOURCE_PERMISSIONS = [
        'view_any_sale::service',
        'view_sale::service',
        'create_sale::service',
        'update_sale::service',
        'replicate_sale::service',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->allPermissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('vendor', 'web')->givePermissionTo(self::VENDOR_ROLE_PERMISSIONS);

        $this->grantApprovedTypePermissions();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<int, string>
     */
    private function allPermissions(): array
    {
        return array_values(array_unique([
            ...self::VENDOR_ROLE_PERMISSIONS,
            ...self::VENDOR_FILAMENT_RESOURCE_PERMISSIONS,
            ...$this->perTypePermissions(ProductType::Rental),
            ...$this->perTypePermissions(ProductType::Sale),
            ...$this->perTypePermissions(ProductType::Digital),
        ]));
    }

    private function grantApprovedTypePermissions(): void
    {
        VendorProfile::query()
            ->with(['approvedTypes', 'user'])
            ->whereHas('approvedTypes')
            ->each(function (VendorProfile $vendorProfile): void {
                $user = $vendorProfile->user;

                if ($user === null) {
                    return;
                }

                $permissions = $vendorProfile->approvedTypes
                    ->flatMap(fn ($approval) => $this->perTypePermissions($approval->product_type))
                    ->merge(self::VENDOR_FILAMENT_RESOURCE_PERMISSIONS)
                    ->unique()
                    ->values()
                    ->all();

                if ($permissions !== []) {
                    $user->givePermissionTo($permissions);
                }
            });
    }

    /**
     * @return array<int, string>
     */
    private function perTypePermissions(ProductType $type): array
    {
        return [
            "service.create.{$type->value}.own",
            "service.update.{$type->value}.own",
            "service.delete.{$type->value}.own",
            "service.publish.{$type->value}.own",
        ];
    }
}

<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Filament\Resources\CustomerAddressResource;
use App\Modules\Identity\Filament\Resources\CustomerProfileResource;
use App\Modules\Identity\Filament\Resources\CustomerResource;
use App\Modules\Identity\Filament\Resources\VendorProfileResource;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;

it('initializes navigation once and separates customer pages from vendors in both locales', function (string $locale): void {
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('view_any_vendor::profile', 'web'));
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $response = $this->get('/admin/vendor-profiles?lang='.$locale)->assertOk();
    expect(substr_count($response->getContent(), 'const syncTables ='))->toBe(1);
    foreach ([CustomerResource::class, CustomerProfileResource::class, CustomerAddressResource::class] as $resource) {
        expect($resource::getNavigationGroup())->toBe(__('admin.nav.groups.customers'))
            ->not->toBe(VendorProfileResource::getNavigationGroup());
    }
})->with(['en', 'ar']);

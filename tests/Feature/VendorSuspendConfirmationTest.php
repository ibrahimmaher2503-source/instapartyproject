<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorProfileResource\Pages\ListVendorProfiles;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('confirms vendor identity status impact and required audit reason in both languages', function (string $locale): void {
    app()->setLocale($locale);
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo([
        Permission::findOrCreate('view_any_vendor::profile', 'web'),
        Permission::findOrCreate('view_vendor::profile', 'web'),
        Permission::findOrCreate('suspend_vendor', 'web'),
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    $vendor = VendorProfile::factory()->approved()->create([
        'business_name' => ['en' => 'Lantern Events QA', 'ar' => 'فوانيس المناسبات'],
        'primary_governorate_id' => $governorate->id,
        'primary_city_id' => $city->id,
    ]);

    Livewire::test(ListVendorProfiles::class)
        ->assertTableActionVisible('suspend', $vendor)
        ->mountTableAction('suspend', $vendor)
        ->assertSee($vendor->public_id)
        ->assertSee(__('identity.modals.suspend_vendor_description', [
            'vendor' => $locale === 'ar' ? 'فوانيس المناسبات' : 'Lantern Events QA',
            'reference' => $vendor->public_id,
            'status' => __('identity.status.approved'),
            'types' => __('identity.placeholders.none'),
        ]))
        ->assertSee(__('identity.forms.vendor_suspension_reason'))
        ->assertSee('required', false);
})->with(['en', 'ar']);

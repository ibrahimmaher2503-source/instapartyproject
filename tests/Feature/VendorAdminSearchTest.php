<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Actions\SearchVendorProfilesAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorProfileResource\Pages\ListVendorProfiles;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('searches vendor administration by public references bilingual names contacts documents coverage and product type', function (): void {
    $role = Role::findOrCreate('super_admin', 'web');
    Permission::findOrCreate('view_any_vendor::profile', 'web');
    $admin = User::factory()->create();
    $admin->assignRole($role);
    $admin->givePermissionTo('view_any_vendor::profile');
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $governorate = Governorate::factory()->create([
        'name' => ['en' => 'Qalyubia QA', 'ar' => 'Qalyubia Arabic QA'],
    ]);
    $city = City::factory()->create([
        'governorate_id' => $governorate->id,
        'name' => ['en' => 'Banha QA', 'ar' => 'Banha Arabic QA'],
    ]);
    $vendorUser = User::factory()->create([
        'name' => 'QA Contact Name',
        'email' => 'searchable-vendor@example.test',
        'phone_e164' => '+201099998888',
    ]);
    $profile = VendorProfile::factory()->approved()->create([
        'user_id' => $vendorUser->id,
        'business_name' => ['en' => 'Lantern Events QA', 'ar' => 'Lantern Arabic QA'],
        'primary_governorate_id' => $governorate->id,
        'primary_city_id' => $city->id,
    ]);
    $document = VendorDocument::factory()->create([
        'vendor_profile_id' => $profile->id,
        'doc_type' => 'national_id',
    ]);
    $hours = VendorBusinessHour::factory()->create(['vendor_profile_id' => $profile->id]);
    $coverage = VendorCoverageArea::factory()->create([
        'vendor_profile_id' => $profile->id,
        'city_id' => $city->id,
    ]);
    $type = VendorApprovedProductType::factory()->create([
        'vendor_profile_id' => $profile->id,
        'product_type' => 'digital',
        'revoked_at' => null,
    ]);

    expect(fn () => app(SearchVendorProfilesAction::class)->execute(User::factory()->create(), ['search' => 'digital']))
        ->toThrow(AuthorizationException::class);

    foreach ([
        $profile->public_id,
        'Lantern Arabic QA',
        'searchable-vendor@example.test',
        $document->public_id,
        'national_id',
        'Banha Arabic QA',
        'digital',
    ] as $search) {
        expect(app(SearchVendorProfilesAction::class)
            ->execute($admin, ['search' => $search])
            ->getCollection()
            ->modelKeys())->toContain($profile->id);
    }

    foreach ([
        $profile->public_id,
        'Lantern',
        'Lantern Arabic',
        'QA Contact',
        'searchable-vendor@example.test',
        '+20 109 999 8888',
        'national_id',
        'Qalyubia QA',
        'Banha Arabic',
        'digital',
    ] as $search) {
        Livewire::test(ListVendorProfiles::class)
            ->searchTable($search)
            ->assertCanSeeTableRecords([$profile]);
    }

    Livewire::test(ListVendorProfiles::class)
        ->searchTable('missing-vendor-query')
        ->assertCanNotSeeTableRecords([$profile]);

});

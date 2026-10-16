<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Filament\Vendor\Pages\VendorServicesListPage;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('renders no raw vendor-portal translation keys on My Services for pending product types', function (string $locale): void {
    app()->setLocale($locale);
    config(['catalog.digital_services_enabled' => true]);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));

    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    $vendor = VendorProfile::factory()->approved()->create([
        'business_name' => ['en' => 'Raw Key Guard Vendor', 'ar' => 'مقدم خدمات حارس المفاتيح'],
        'address_line' => ['en' => '1 Guard Street', 'ar' => '١ شارع الحراسة'],
        'business_type' => 'individual',
        'national_id' => '29902021234567',
        'primary_governorate_id' => $governorate->id,
        'primary_city_id' => $city->id,
        'bank_name' => 'Guard Bank',
        'bank_account_holder' => 'Raw Key Guard Vendor',
        'bank_iban' => 'EG380019000500000000263180003',
    ]);

    VendorBusinessHour::factory()->create(['vendor_profile_id' => $vendor->id]);
    VendorCoverageArea::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'city_id' => $vendor->primary_city_id,
    ]);

    foreach (['national_id', 'iban_proof'] as $type) {
        VendorDocument::factory()->approved()->create([
            'vendor_profile_id' => $vendor->id,
            'doc_type' => $type,
            'expires_at' => today(),
        ]);
    }

    $this->actingAs($vendor->user, 'vendor');

    // Every product type is pending, so the info banner must show the translated sentence.
    $pending = __('vendor-portal.services.pending_product_types', [
        'types' => collect(ProductType::cases())
            ->map(fn (ProductType $type): string => $type->label())
            ->implode(__('vendor-portal.services.product_type_separator')),
    ]);

    $html = Livewire::test(VendorServicesListPage::class)
        ->assertSee($pending)
        ->assertDontSee('vendor-portal.services.pending_product_types')
        ->assertDontSee('vendor-portal.services.')
        ->assertDontSee('vendor-portal.dashboard.')
        ->html();

    // Guard the whole namespace section: a raw key leak renders as dot notation, which
    // never appears in legitimate markup (panel URLs use "/vendor-portal/").
    expect($html)->not->toContain('vendor-portal.services.')
        ->and($html)->not->toContain('vendor-portal.dashboard.');
})->with(['en', 'ar']);

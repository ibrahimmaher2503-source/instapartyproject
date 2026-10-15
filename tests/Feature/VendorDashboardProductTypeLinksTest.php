<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Filament\Vendor\Pages\VendorServicesListPage;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorDigitalServiceResource;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Filament\Vendor\Widgets\VendorStatsOverviewWidget;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
});

/** @param list<ProductType> $types */
function vendorWithApprovedServiceTypes(array $types): VendorProfile
{
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);

    foreach ($types as $type) {
        VendorApprovedProductType::factory()
            ->for($vendor, 'vendorProfile')
            ->forType($type)
            ->create();
    }

    return $vendor;
}

function expectServicesListCreateLinks(string $html, bool $rental, bool $sale): void
{
    expect(substr_count($html, 'href="'.VendorRentalServiceResource::getUrl('create', panel: 'vendor').'"'))
        ->toBe($rental ? 2 : 0);
    expect(substr_count($html, 'href="'.VendorSaleServiceResource::getUrl('create', panel: 'vendor').'"'))
        ->toBe($sale ? 2 : 0);
}

it('does not link a sale-only vendor to rental services', function (): void {
    $vendor = vendorWithApprovedServiceTypes([ProductType::Sale]);
    $this->actingAs($vendor->user, 'vendor');

    Livewire::test(VendorStatsOverviewWidget::class)
        ->assertSee('href="'.VendorSaleServiceResource::getUrl(panel: 'vendor').'"', false)
        ->assertDontSee('href="'.VendorRentalServiceResource::getUrl(panel: 'vendor').'"', false);
});

it('does not link a sale vendor to a revoked rental service type', function (): void {
    $vendor = vendorWithApprovedServiceTypes([ProductType::Sale]);
    VendorApprovedProductType::factory()
        ->for($vendor, 'vendorProfile')
        ->forType(ProductType::Rental)
        ->revoked()
        ->create();
    $this->actingAs($vendor->user, 'vendor');

    Livewire::test(VendorStatsOverviewWidget::class)
        ->assertSee('href="'.VendorSaleServiceResource::getUrl(panel: 'vendor').'"', false)
        ->assertDontSee('href="'.VendorRentalServiceResource::getUrl(panel: 'vendor').'"', false);
});

it('shows only the sale create actions on a sale-only vendor services page', function (): void {
    $vendor = vendorWithApprovedServiceTypes([ProductType::Sale]);
    $this->actingAs($vendor->user, 'vendor');

    expectServicesListCreateLinks(Livewire::test(VendorServicesListPage::class)->html(), rental: false, sale: true);
});

it('shows only the rental create actions on a rental-only vendor services page', function (): void {
    $vendor = vendorWithApprovedServiceTypes([ProductType::Rental]);
    $this->actingAs($vendor->user, 'vendor');

    expectServicesListCreateLinks(Livewire::test(VendorServicesListPage::class)->html(), rental: true, sale: false);
});

it('hides create actions for revoked types on a vendor services page', function (): void {
    $vendor = vendorWithApprovedServiceTypes([ProductType::Sale]);
    VendorApprovedProductType::factory()
        ->for($vendor, 'vendorProfile')
        ->forType(ProductType::Rental)
        ->revoked()
        ->create();
    $this->actingAs($vendor->user, 'vendor');

    expectServicesListCreateLinks(Livewire::test(VendorServicesListPage::class)->html(), rental: false, sale: true);
});

it('does not link a rental-only vendor to sale services', function (): void {
    $vendor = vendorWithApprovedServiceTypes([ProductType::Rental]);
    $this->actingAs($vendor->user, 'vendor');

    Livewire::test(VendorStatsOverviewWidget::class)
        ->assertSee('href="'.VendorRentalServiceResource::getUrl(panel: 'vendor').'"', false)
        ->assertDontSee('href="'.VendorSaleServiceResource::getUrl(panel: 'vendor').'"', false);
});

it('links vendors to each approved rental and sale service type', function (): void {
    $vendor = vendorWithApprovedServiceTypes([ProductType::Rental, ProductType::Sale]);
    $this->actingAs($vendor->user, 'vendor');

    Livewire::test(VendorStatsOverviewWidget::class)
        ->assertSee('href="'.VendorRentalServiceResource::getUrl(panel: 'vendor').'"', false)
        ->assertSee('href="'.VendorSaleServiceResource::getUrl(panel: 'vendor').'"', false);
});

it('links approved digital services only while digital services are enabled', function (): void {
    $vendor = vendorWithApprovedServiceTypes([ProductType::Digital]);
    $this->actingAs($vendor->user, 'vendor');

    config(['catalog.digital_services_enabled' => false]);
    Livewire::test(VendorStatsOverviewWidget::class)
        ->assertDontSee('href="'.VendorDigitalServiceResource::getUrl(panel: 'vendor').'"', false);

    config(['catalog.digital_services_enabled' => true]);
    Livewire::test(VendorStatsOverviewWidget::class)
        ->assertSee('href="'.VendorDigitalServiceResource::getUrl(panel: 'vendor').'"', false);
});

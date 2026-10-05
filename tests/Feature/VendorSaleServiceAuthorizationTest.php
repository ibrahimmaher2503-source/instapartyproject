<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
});

it('allows an approved sale vendor to open the sale service create page', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()
        ->for($vendor, 'vendorProfile')
        ->forType(ProductType::Sale)
        ->create();

    $this->actingAs($vendor->user, 'vendor');

    expect(VendorSaleServiceResource::canCreate())->toBeTrue();

    $this->get('/vendor-portal/vendor-sale-services/create')->assertOk();
});

it('keeps the sale service create page forbidden without active sale approval', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();

    $this->actingAs($vendor->user, 'vendor');

    expect(VendorSaleServiceResource::canCreate())->toBeFalse();

    $this->get('/vendor-portal/vendor-sale-services/create')->assertForbidden();
});

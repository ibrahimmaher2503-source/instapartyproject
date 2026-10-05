<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Filament\Vendor\Pages\VendorImportDigitalServicesPage;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorDigitalServiceResource;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Laravel\Sanctum\Sanctum;

it('removes disabled digital services from customer entry points while preserving rows', function (): void {
    config(['catalog.digital_services_enabled' => false]);
    config(['scout.driver' => 'database']);

    $digital = Service::factory()->digital()->published()->create();
    $rental = Service::factory()->rental()->published()->create();

    $this->getJson('/api/v1/customer/services')
        ->assertOk()
        ->assertJsonMissing(['public_id' => $digital->public_id])
        ->assertJsonFragment(['public_id' => $rental->public_id]);
    $this->getJson('/api/v1/customer/services/'.$digital->public_id)->assertNotFound();
    $this->get('/en/services/'.$digital->public_id)->assertNotFound();
    $this->get('/en/search?product_type=digital')
        ->assertOk()
        ->assertDontSee('value="digital"', false);

    expect($digital->fresh())->not->toBeNull();

    config(['catalog.digital_services_enabled' => true]);

    $this->getJson('/api/v1/customer/services/'.$digital->public_id)->assertOk();
    $this->get('/en/services/'.$digital->public_id)->assertOk();
});

it('blocks disabled digital vendor operations without deleting existing data', function (): void {
    config(['catalog.digital_services_enabled' => false]);

    $vendor = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType(ProductType::Digital)->create([
        'vendor_profile_id' => $vendor->id,
    ]);
    $digital = Service::factory()->digital()->create(['vendor_profile_id' => $vendor->id]);

    Sanctum::actingAs($vendor->user, ['*'], 'web');

    expect(VendorDigitalServiceResource::canAccess())->toBeFalse()
        ->and(VendorImportDigitalServicesPage::canAccess())->toBeFalse();

    $this->getJson('/api/v1/vendor/services?type=digital')->assertNotFound();
    $this->getJson('/api/v1/vendor/services/'.$digital->public_id)->assertNotFound();
    $this->getJson('/api/v1/vendor/catalog/import/template/digital')->assertNotFound();
    $this->postJson('/api/v1/vendor/services/digital', [])->assertForbidden();

    expect($digital->fresh())->not->toBeNull();
});

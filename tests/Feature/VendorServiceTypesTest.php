<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;

it('counts only the vendors displayable service types across all pages and removes the last type', function (string $locale, bool $digitalEnabled): void {
    config(['catalog.digital_services_enabled' => $digitalEnabled]);
    $vendor = VendorProfile::factory()->approved()->create();
    foreach (ProductType::cases() as $type) {
        VendorApprovedProductType::factory()->forType($type)->create(['vendor_profile_id' => $vendor->id]);
        Service::factory()->{$type->value}()->create(['vendor_profile_id' => $vendor->id]);
    }
    Service::factory()->rental()->published()->count(13)->create(['vendor_profile_id' => $vendor->id]);
    $sale = Service::factory()->sale()->published()->create(['vendor_profile_id' => $vendor->id]);
    $digital = Service::factory()->digital()->published()->create(['vendor_profile_id' => $vendor->id]);
    Service::factory()->published()->count(2)->create();

    $url = "/{$locale}/vendors/{$vendor->slug}";
    $response = $this->get($url)->assertOk();
    expect($response->viewData('serviceTypeCounts')->all())->toBe($digitalEnabled
        ? ['digital' => 1, 'rental' => 13, 'sale' => 1]
        : ['rental' => 13, 'sale' => 1]);
    expect($response->viewData('vendor')->services_count)->toBe($digitalEnabled ? 15 : 14)
        ->and($response->viewData('services')->count())->toBe($digitalEnabled ? 15 : 14);
    $response->assertSee('data-vendor-service-type="rental"', false)
        ->assertSee('data-vendor-service-type="sale"', false);
    if (! $digitalEnabled) {
        $response->assertDontSee('data-vendor-service-type="digital"', false);
        expect(collect($response->viewData('services')->items())->contains('public_id', $digital->public_id))->toBeFalse();
    }

    $sale->delete();
    $response = $this->get($url)->assertOk()->assertDontSee('data-vendor-service-type="sale"', false);
    expect($response->viewData('serviceTypeCounts')->count())->toBe($digitalEnabled ? 2 : 1)
        ->and($response->viewData('vendor')->services_count)->toBe($digitalEnabled ? 14 : 13)
        ->and($digital->fresh())->not->toBeNull();
})->with(['en', 'ar'])->with([true, false]);

it('shows an empty vendor without approved-only type badges', function (string $locale): void {
    $vendor = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType(ProductType::Rental)->create(['vendor_profile_id' => $vendor->id]);
    Service::factory()->rental()->create(['vendor_profile_id' => $vendor->id]);
    $response = $this->get("/{$locale}/vendors/{$vendor->slug}")->assertOk();
    expect($response->viewData('vendor')->services_count)->toBe(0)
        ->and($response->viewData('serviceTypeCounts'))->toBeEmpty();
    $response->assertDontSee('data-vendor-types-count', false)
        ->assertDontSee('data-vendor-service-type=', false);
})->with(['en', 'ar']);

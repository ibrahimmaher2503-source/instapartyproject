<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\ListVendorCategoriesAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\ChangesRequestedState;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

it('returns a non-enumerating 404 when a vendor resubmits a foreign service', function (): void {
    $owner = VendorProfile::factory()->approved()->create();
    $attacker = VendorProfile::factory()->approved()->create();
    $admin = User::factory()->superAdmin()->create();
    $service = Service::factory()->rental()->changesRequested()->create([
        'vendor_profile_id' => $owner->id,
    ]);
    $changeRequest = createServiceChangeRequest($service, $admin, 'base_price_minor');

    Sanctum::actingAs($attacker->user);

    $this->postJson(
        "/api/v1/vendor/services/{$service->public_id}/resubmit",
        ['changed_fields' => ['vendor_profile_id' => $attacker->id]],
        ['Idempotency-Key' => 'foreign-service-resubmit'],
    )->assertNotFound();

    expect($service->refresh()->vendor_profile_id)->toBe($owner->id)
        ->and($service->status)->toBeInstanceOf(ChangesRequestedState::class)
        ->and($changeRequest->refresh()->status->value)->toBe('open');
});

it('rejects service resubmission fields outside the requested safe allowlist', function (): void {
    $owner = VendorProfile::factory()->approved()->create();
    $admin = User::factory()->superAdmin()->create();
    $service = Service::factory()->rental()->changesRequested()->create([
        'vendor_profile_id' => $owner->id,
    ]);
    $changeRequest = createServiceChangeRequest($service, $admin, 'base_price_minor');
    $originalOwnerId = $service->vendor_profile_id;

    Sanctum::actingAs($owner->user);

    $this->postJson(
        "/api/v1/vendor/services/{$service->public_id}/resubmit",
        ['changed_fields' => ['vendor_profile_id' => $originalOwnerId + 1]],
        ['Idempotency-Key' => 'unsafe-service-resubmit'],
    )->assertUnprocessable();

    expect($service->refresh()->vendor_profile_id)->toBe($originalOwnerId)
        ->and($service->status)->toBeInstanceOf(ChangesRequestedState::class)
        ->and($changeRequest->refresh()->status->value)->toBe('open');
});

it('accepts only active categories matching the requested product type', function (): void {
    foreach (ProductType::cases() as $type) {
        $vendor = VendorProfile::factory()->approved()->create();
        VendorApprovedProductType::factory()->forType($type)->create([
            'vendor_profile_id' => $vendor->id,
        ]);

        $wrongType = $type === ProductType::Sale ? ProductType::Rental : ProductType::Sale;
        $wrongCategory = Category::factory()->create([
            'allowed_product_types' => [$wrongType->value],
        ]);
        $inactiveCategory = Category::factory()->create([
            'allowed_product_types' => [$type->value],
            'is_active' => false,
        ]);

        Sanctum::actingAs($vendor->user);

        foreach ([$wrongCategory, $inactiveCategory] as $category) {
            $this->postJson(
                "/api/v1/vendor/services/{$type->value}",
                servicePayload($type, $category->id),
            )->assertUnprocessable();
        }
    }
});

it('lists categories scoped to vendor approvals and requested product type', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType(ProductType::Rental)->create([
        'vendor_profile_id' => $vendor->id,
    ]);
    $rentalRoot = Category::factory()->create([
        'allowed_product_types' => [ProductType::Rental->value],
    ]);
    $saleRoot = Category::factory()->create([
        'allowed_product_types' => [ProductType::Sale->value],
    ]);
    $rentalChild = Category::factory()->create([
        'parent_id' => $rentalRoot->id,
        'allowed_product_types' => [ProductType::Rental->value],
    ]);
    $saleChild = Category::factory()->create([
        'parent_id' => $rentalRoot->id,
        'allowed_product_types' => [ProductType::Sale->value],
    ]);

    Sanctum::actingAs($vendor->user);

    $this->getJson('/api/v1/vendor/categories')
        ->assertOk()
        ->assertJsonPath('data.0.id', $rentalRoot->id)
        ->assertJsonMissing(['id' => $saleRoot->id]);

    $this->getJson('/api/v1/vendor/categories?product_type=sale')
        ->assertOk()
        ->assertJsonPath('data', []);

    $categories = app(ListVendorCategoriesAction::class)->execute($vendor);

    expect($categories->firstOrFail()->children->pluck('id')->all())
        ->toContain($rentalChild->id)
        ->not->toContain($saleChild->id);
});

function createServiceChangeRequest(Service $service, User $admin, string $fieldPath): ChangeRequest
{
    $changeRequest = ChangeRequest::query()->create([
        'public_id' => (string) Str::ulid(),
        'subject_type' => 'service',
        'subject_id' => $service->id,
        'requested_by_admin_id' => $admin->id,
        'status' => 'open',
        'cycle_number' => 1,
    ]);

    $changeRequest->items()->create([
        'public_id' => (string) Str::ulid(),
        'field_path' => $fieldPath,
        'requested_change_en' => 'Update the value.',
        'requested_change_ar' => 'حدّث القيمة.',
        'item_status' => 'pending',
    ]);

    return $changeRequest;
}

/** @return array<string, mixed> */
function servicePayload(ProductType $type, int $categoryId): array
{
    $payload = [
        'name' => ['en' => 'Security regression service', 'ar' => 'خدمة اختبار أمني'],
        'short_description' => ['en' => 'Safe category validation.', 'ar' => 'تحقق آمن من التصنيف.'],
        'category_id' => $categoryId,
        'base_price_minor' => 10000,
    ];

    return $payload + match ($type) {
        ProductType::Rental => [
            'default_rental_duration_hours' => 4,
        ],
        ProductType::Sale => [],
        ProductType::Digital => [
            'delivery_method' => 'link',
        ],
    };
}

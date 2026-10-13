<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Services\CategoryFieldValidationService;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceDigitalDetail;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('saves a published legacy service without a newly required field and keeps it published', function (string $locale, ProductType $type): void {
    config(['catalog.digital_services_enabled' => true]);
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $vendor = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType($type)->create(['vendor_profile_id' => $vendor->id]);
    $vendor->user->givePermissionTo(Permission::findOrCreate("service.update.{$type->value}.own", 'web'));
    $root = Category::factory()->create(['parent_id' => null, 'allowed_product_types' => [$type->value]]);
    $category = Category::factory()->create(['parent_id' => $root->id, 'allowed_product_types' => [$type->value]]);
    CategoryFieldSchema::factory()->create([
        'category_id' => $category->id, 'product_type' => $type, 'field_key' => 'flavor',
        'field_label' => ['en' => 'Flavor', 'ar' => 'النكهة'], 'field_type' => 'select',
        'options' => [['value' => 'vanilla', 'label' => ['en' => 'Vanilla', 'ar' => 'فانيليا']]],
        'is_required' => true, 'validation_rules' => null,
    ]);
    CategoryFieldSchema::factory()->create([
        'category_id' => $category->id, 'product_type' => $type, 'field_key' => 'requires_setup',
        'field_label' => ['en' => 'Requires setup', 'ar' => 'يتطلب تجهيزًا'], 'field_type' => 'boolean',
        'is_required' => true, 'options' => null, 'validation_rules' => null,
    ]);
    $service = Service::factory()->published()->create([
        'vendor_profile_id' => $vendor->id, 'category_id' => $category->id, 'product_type' => $type,
        'category_field_values' => null, 'base_price_minor' => 25050,
    ]);
    match ($type) {
        ProductType::Rental => ServiceRentalDetail::factory()->create(['service_id' => $service->id]),
        ProductType::Sale => ServiceSaleDetail::factory()->create(['service_id' => $service->id]),
        ProductType::Digital => ServiceDigitalDetail::factory()->create(['service_id' => $service->id, 'delivery_method' => 'email']),
    };
    $this->actingAs($vendor->user, 'vendor');
    $classType = ucfirst($type->value);
    $edit = "App\\Modules\\Catalog\\Filament\\Vendor\\Resources\\Vendor{$classType}ServiceResource\\Pages\\EditVendor{$classType}Service";
    Livewire::test($edit, ['record' => $service->public_id])->call('save')->assertHasNoFormErrors();
    expect($service->refresh()->status)->toBeInstanceOf(PublishedState::class)
        ->and($service->base_price_minor)->toBe(25050)
        ->and($service->category_field_values['requires_setup'] ?? null)->toBeNull();
    Livewire::test($edit, ['record' => $service->public_id])->fillForm(['category_field_values.flavor' => 'unknown'])
        ->call('save')->assertHasFormErrors(['category_field_values.flavor']);
    Livewire::test($edit, ['record' => $service->public_id])->fillForm(['category_field_values.flavor' => 'vanilla'])
        ->call('save')->assertHasNoFormErrors();
    expect($service->refresh()->category_field_values['flavor'])->toBe('vanilla');
    Livewire::test($edit, ['record' => $service->public_id])->fillForm(['category_field_values.flavor' => null])
        ->call('save')->assertHasFormErrors(['category_field_values.flavor']);
})->with(['en', 'ar'])->with(ProductType::cases());

it('requires current schema values for creation and category changes and validates newly supplied legacy values', function (ProductType $type): void {
    config(['catalog.digital_services_enabled' => true]);
    $category = Category::factory()->create();
    $otherCategory = Category::factory()->create();
    foreach ([$category, $otherCategory] as $target) {
        CategoryFieldSchema::factory()->create([
            'category_id' => $target->id, 'product_type' => $type, 'field_key' => 'capacity',
            'field_type' => 'number', 'is_required' => true, 'validation_rules' => ['integer', 'min:1'],
        ]);
    }
    $service = Service::factory()->create(['category_id' => $category->id, 'product_type' => $type, 'category_field_values' => []]);
    $validator = app(CategoryFieldValidationService::class);
    expect($validator->validateValues($category->id, $type, [], existingService: $service))->toBe([]);
    expect(fn () => $validator->validateValues($category->id, $type, []))->toThrow(ValidationException::class);
    $unsavedService = new Service(['category_id' => $category->id, 'product_type' => $type, 'category_field_values' => []]);
    expect(fn () => $validator->validateValues($category->id, $type, [], existingService: $unsavedService))
        ->toThrow(ValidationException::class);
    expect(fn () => $validator->validateValues($otherCategory->id, $type, [], existingService: $service))->toThrow(ValidationException::class);
    expect(fn () => $validator->validateValues($category->id, $type, ['capacity' => 0], existingService: $service))->toThrow(ValidationException::class);
    expect(fn () => $validator->validateValues($category->id, $type, ['foreign' => 'value'], existingService: $service))->toThrow(ValidationException::class);
    $classType = ucfirst($type->value);
    $actionClass = "App\\Modules\\Catalog\\Application\\Actions\\Update{$classType}ServiceAction";
    expect(fn () => app($actionClass)->execute($service, $service->vendor, ['category_id' => $otherCategory->id]))
        ->toThrow(ValidationException::class);
    expect($service->refresh()->category_id)->toBe($category->id);
    $service->update(['category_field_values' => ['capacity' => 2]]);
    expect(fn () => $validator->validateValues($category->id, $type, [], existingService: $service))->toThrow(ValidationException::class);
})->with(ProductType::cases());

it('rejects an existing choice removed from the current category schema', function (ProductType $type): void {
    $category = Category::factory()->create();
    CategoryFieldSchema::factory()->create([
        'category_id' => $category->id, 'product_type' => $type, 'field_key' => 'flavor',
        'field_type' => 'select', 'is_required' => true, 'validation_rules' => null,
        'options' => [['value' => 'vanilla', 'label' => ['en' => 'Vanilla', 'ar' => 'فانيليا']]],
    ]);
    $service = Service::factory()->create([
        'category_id' => $category->id, 'product_type' => $type, 'category_field_values' => ['flavor' => 'removed'],
    ]);
    $classType = ucfirst($type->value);
    $actionClass = "App\\Modules\\Catalog\\Application\\Actions\\Update{$classType}ServiceAction";
    expect(fn () => app($actionClass)->execute($service, $service->vendor, ['category_field_values' => ['flavor' => 'removed']]))
        ->toThrow(ValidationException::class);
    expect($service->refresh()->category_field_values)->toBe(['flavor' => 'removed']);
})->with(ProductType::cases());

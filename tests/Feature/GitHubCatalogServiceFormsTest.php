<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    config(['catalog.digital_services_enabled' => true]);
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any', 'view', 'create', 'update'] as $operation) {
        foreach (['rental::service', 'sale::service', 'digital::service', 'category::field::schema', 'vendor::profile'] as $resource) {
            $admin->givePermissionTo(Permission::findOrCreate($operation.'_'.$resource, 'web'));
        }
    }
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('exposes category-specific fields and converts major currency input on each admin service form', function (string $locale, ProductType $type): void {
    app()->setLocale($locale);
    $category = Category::factory()->create(['allowed_product_types' => [$type->value]]);
    CategoryFieldSchema::factory()->create([
        'category_id' => $category->id, 'product_type' => $type, 'field_key' => 'flavor', 'field_type' => 'select',
        'field_label' => ['en' => 'Flavor', 'ar' => 'النكهة'], 'is_required' => true,
        'options' => [['value' => 'vanilla', 'label' => ['en' => 'Vanilla', 'ar' => 'فانيليا']]], 'validation_rules' => null,
    ]);
    CategoryFieldSchema::factory()->create([
        'category_id' => $category->id, 'product_type' => $type, 'field_key' => 'extras', 'field_type' => 'multiselect',
        'field_label' => ['en' => 'Extras', 'ar' => 'الإضافات'], 'is_required' => false,
        'options' => [['value' => 'candles', 'label' => ['en' => 'Candles', 'ar' => 'شموع']]], 'validation_rules' => null,
    ]);
    $vendor = VendorProfile::factory()->approved()->create();
    $classType = ucfirst($type->value);
    $create = "App\\Modules\\Catalog\\Filament\\Resources\\{$classType}ServiceResource\\Pages\\Create{$classType}Service";
    $edit = "App\\Modules\\Catalog\\Filament\\Resources\\{$classType}ServiceResource\\Pages\\Edit{$classType}Service";
    $component = Livewire::test($create)->set('data.category_id', $category->id)
        ->assertFormFieldExists('category_field_values.flavor', fn (Select $field): bool => $field->getOptions() === [
            'vanilla' => $locale === 'ar' ? 'فانيليا' : 'Vanilla',
        ]);
    $component->fillForm([
        'vendor_profile_id' => $vendor->id, 'name' => ['en' => 'Schema service', 'ar' => 'خدمة حقول التصنيف'],
        'short_description' => ['en' => 'Short description', 'ar' => 'وصف مختصر'],
        'base_price_minor' => '250.50', 'category_field_values.flavor' => 'vanilla', 'category_field_values.extras' => ['removed'],
        'rentalDetail.default_rental_duration_hours' => 4,
        'saleDetail' => ['is_made_to_order' => true, 'lead_time_hours' => 24, 'stock_quantity' => 10, 'customization_fields' => []],
        'digitalDetail.delivery_method' => 'email',
    ])->call('create')->assertHasFormErrors(['category_field_values.extras.0']);
    expect(Service::query()->count())->toBe(0);
    $component->fillForm(['category_field_values.extras' => ['candles']])
        ->call('create')->assertHasNoFormErrors();
    $service = Service::query()->sole();
    expect($service->slug)->not->toBeEmpty()
        ->and($service->base_price_minor)->toBe(25050)
        ->and($service->category_field_values)->toBe(['flavor' => 'vanilla', 'extras' => ['candles']]);
    if ($type === ProductType::Rental) {
        expect($service->rentalDetail->setup_time_minutes)->toBe(0)
            ->and($service->rentalDetail->teardown_time_minutes)->toBe(0)
            ->and($service->rentalDetail->security_deposit_minor)->toBe(0);
    }
    Livewire::test($edit, ['record' => $service->getRouteKey()])->assertFormSet([
        'base_price_minor' => '250.50', 'category_field_values.flavor' => 'vanilla',
    ])->call('save')->assertHasNoFormErrors();
    expect($service->refresh()->base_price_minor)->toBe(25050);
})->with(['en', 'ar'])->with(ProductType::cases());

it('rejects invalid major prices and empty bilingual names before creating an admin service', function (ProductType $type): void {
    $category = Category::factory()->create(['allowed_product_types' => [$type->value]]);
    $vendor = VendorProfile::factory()->approved()->create();
    $classType = ucfirst($type->value);
    $create = "App\\Modules\\Catalog\\Filament\\Resources\\{$classType}ServiceResource\\Pages\\Create{$classType}Service";
    $component = Livewire::test($create)->fillForm([
        'category_id' => $category->id, 'vendor_profile_id' => $vendor->id,
        'name' => ['en' => '', 'ar' => ''],
        'short_description' => ['en' => 'Short description', 'ar' => 'وصف مختصر'],
        'base_price_minor' => '250.001',
        'rentalDetail.default_rental_duration_hours' => 4, 'digitalDetail.delivery_method' => 'email',
    ])->call('create')->assertHasFormErrors(['name.en', 'name.ar', 'base_price_minor']);
    $component->fillForm(['name' => ['en' => 'Service', 'ar' => 'خدمة']]);
    foreach (['-1', '1e2', '92233720368547758.08'] as $price) {
        $component->fillForm(['base_price_minor' => $price])->call('create')->assertHasFormErrors(['base_price_minor']);
    }
    expect(Service::query()->count())->toBe(0);
})->with(ProductType::cases());

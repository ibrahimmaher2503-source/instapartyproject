<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Filament\Resources\RentalServiceResource\Pages\EditRentalService;
use App\Modules\Catalog\Http\Resources\CustomerServiceDetailResource;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('round trips vendor bilingual long descriptions through create, edit and the customer detail projection', function (ProductType $type, string $locale): void {
    config(['catalog.digital_services_enabled' => true]);
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));

    $vendor = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType($type)->create(['vendor_profile_id' => $vendor->id]);
    $vendor->user->givePermissionTo(Permission::findOrCreate("service.update.{$type->value}.own", 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $vendor->user->unsetRelation('permissions');

    $root = Category::factory()->create(['parent_id' => null, 'allowed_product_types' => [$type->value]]);
    $category = Category::factory()->create(['parent_id' => $root->id, 'allowed_product_types' => [$type->value]]);
    $classType = ucfirst($type->value);
    $base = "App\\Modules\\Catalog\\Filament\\Vendor\\Resources\\Vendor{$classType}ServiceResource\\Pages\\";
    $create = $base."CreateVendor{$classType}Service";
    $edit = $base."EditVendor{$classType}Service";
    $longDescription = [
        'en' => 'A detailed English description for the service.',
        'ar' => 'وصف عربي تفصيلي للخدمة.',
    ];

    $this->actingAs($vendor->user, 'vendor');
    $form = Livewire::test($create)->fillForm([
        'name' => ['en' => 'Detailed service', 'ar' => 'خدمة تفصيلية'],
        'short_description' => ['en' => 'Short copy', 'ar' => 'وصف مختصر'],
        'long_description' => ['en' => $longDescription['en'], 'ar' => ''],
        'main_category_id' => $root->id,
        'category_id' => $category->id,
        'base_price_minor' => '250',
        'rentalDetail' => ['default_rental_duration_hours' => 4],
        'saleDetail' => ['is_made_to_order' => true],
        'digitalDetail' => ['delivery_method' => 'email'],
    ])->call('create')->assertHasFormErrors(['long_description.ar']);
    expect(Service::query()->count())->toBe(0);

    $form->fillForm(['long_description.ar' => $longDescription['ar']])->call('create')->assertHasNoFormErrors();

    $service = Service::query()->sole();
    expect($service->getTranslation('long_description', 'en'))->toBe($longDescription['en'])
        ->and($service->getTranslation('long_description', 'ar'))->toBe($longDescription['ar']);

    Livewire::test($edit, ['record' => $service->public_id])
        ->assertFormSet(['long_description.en' => $longDescription['en'], 'long_description.ar' => $longDescription['ar']]);

    $detail = CustomerServiceDetailResource::make($service)->toArray(request());
    expect($detail['description'])->toBe($longDescription[$locale]);
})->with(ProductType::cases())->with(['en', 'ar']);

it('hydrates and saves rental category attributes and physical dimensions in the admin edit form', function (string $locale): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any', 'view', 'create', 'update'] as $operation) {
        $admin->givePermissionTo(Permission::findOrCreate($operation.'_rental::service', 'web'));
    }
    $this->actingAs($admin);

    $category = Category::factory()->create(['allowed_product_types' => [ProductType::Rental->value]]);
    foreach ([
        ['dimensions', 'text', ['en' => 'Dimensions', 'ar' => 'الأبعاد']],
        ['capacity', 'number', ['en' => 'Capacity', 'ar' => 'السعة']],
    ] as [$key, $fieldType, $labels]) {
        CategoryFieldSchema::factory()->create([
            'category_id' => $category->id,
            'product_type' => ProductType::Rental,
            'field_key' => $key,
            'field_type' => $fieldType,
            'field_label' => $labels,
            'is_required' => false,
            'validation_rules' => null,
        ]);
    }

    $service = Service::factory()->rental()->create([
        'category_id' => $category->id,
        'category_field_values' => ['dimensions' => '4 x 3 x 2 m', 'capacity' => 40],
    ]);
    ServiceRentalDetail::factory()->create([
        'service_id' => $service->id,
        'default_rental_duration_hours' => 4,
        'length_m' => '4.50',
        'width_m' => '3.25',
        'height_m' => '2.00',
    ]);

    $edit = Livewire::test(EditRentalService::class, ['record' => $service->getRouteKey()])
        ->assertFormFieldExists('category_field_values.dimensions')
        ->assertFormFieldExists('category_field_values.capacity')
        ->assertFormFieldExists('rentalDetail.length_m')
        ->assertFormFieldExists('rentalDetail.width_m')
        ->assertFormFieldExists('rentalDetail.height_m')
        ->assertFormSet([
            'category_field_values.dimensions' => '4 x 3 x 2 m',
            'category_field_values.capacity' => '40',
            'rentalDetail.length_m' => '4.50',
            'rentalDetail.width_m' => '3.25',
            'rentalDetail.height_m' => '2.00',
        ]);

    $edit->fillForm([
        'category_field_values.dimensions' => '5 x 3 x 2 m',
        'category_field_values.capacity' => '48',
        'rentalDetail.length_m' => '5.00',
        'rentalDetail.width_m' => '3.50',
        'rentalDetail.height_m' => '2.25',
    ])->call('save')->assertHasNoFormErrors();

    $service = $service->refresh()->load('rentalDetail');
    expect($service->category_field_values)->toEqualCanonicalizing(['dimensions' => '5 x 3 x 2 m', 'capacity' => '48'])
        ->and($service->rentalDetail->length_m)->toBe('5.00')
        ->and($service->rentalDetail->width_m)->toBe('3.50')
        ->and($service->rentalDetail->height_m)->toBe('2.25');

    $detail = CustomerServiceDetailResource::make($service)->toArray(request());
    expect($detail['rental']['length_m'])->toBe('5.00')
        ->and($detail['rental']['width_m'])->toBe('3.50')
        ->and($detail['rental']['height_m'])->toBe('2.25')
        ->and($detail['category_fields'])->toContain(
            ['key' => 'dimensions', 'label' => ['en' => 'Dimensions', 'ar' => 'الأبعاد'][$locale], 'value' => '5 x 3 x 2 m'],
        )
        ->and($detail['category_fields'])->toContain(
            ['key' => 'capacity', 'label' => ['en' => 'Capacity', 'ar' => 'السعة'][$locale], 'value' => '48'],
        );
})->with(['en', 'ar']);

<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Services\CategoryFieldValidationService;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Filament\Resources\CategoryFieldSchemaResource\Pages\EditCategoryFieldSchema;
use App\Modules\Catalog\Filament\Resources\DigitalServiceResource\Pages\CreateDigitalService;
use App\Modules\Catalog\Filament\Resources\OccasionResource\Pages\CreateOccasion;
use App\Modules\Catalog\Filament\Resources\OccasionResource\Pages\EditOccasion;
use App\Modules\Catalog\Filament\Resources\RentalServiceResource\Pages\EditRentalService;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    config(['catalog.digital_services_enabled' => true]);
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any', 'view', 'create', 'update'] as $operation) {
        foreach (['rental::service', 'sale::service', 'digital::service', 'category::field::schema', 'vendor::profile', 'occasion'] as $resource) {
            $admin->givePermissionTo(Permission::findOrCreate($operation.'_'.$resource, 'web'));
        }
    }
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('roundtrips bilingual structured schema options without flattening them', function (string $locale): void {
    app()->setLocale($locale);
    $options = [
        ['value' => 'vanilla', 'label' => ['en' => 'Vanilla', 'ar' => 'فانيليا']],
        ['value' => 'chocolate', 'label' => ['en' => 'Chocolate', 'ar' => 'شوكولاتة']],
    ];
    $schema = CategoryFieldSchema::factory()->create([
        'field_key' => 'flavor', 'field_type' => 'select', 'field_label' => ['en' => 'Flavor', 'ar' => 'النكهة'],
        'options' => $options, 'validation_rules' => null,
    ]);
    Livewire::test(EditCategoryFieldSchema::class, ['record' => $schema->getRouteKey()])
        ->assertFormFieldExists('options', fn (Repeater $field): bool => array_values($field->getState()) === $options)
        ->assertSee(__('catalog.language_arabic'))->assertDontSee('Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©')
        ->call('save')->assertHasNoFormErrors();
    expect($schema->refresh()->options)->toBe($options);
})->with(['en', 'ar']);

it('converts supported legacy schema options without changing their effective choices', function (array $options): void {
    $schema = CategoryFieldSchema::factory()->create(['field_type' => 'select', 'options' => $options, 'validation_rules' => null]);
    $validator = app(CategoryFieldValidationService::class);
    $before = [$validator->optionsFor($schema, 'en'), $validator->optionsFor($schema, 'ar')];
    Livewire::test(EditCategoryFieldSchema::class, ['record' => $schema->getRouteKey()])
        ->call('save')->assertHasNoFormErrors();
    expect([$validator->optionsFor($schema->refresh(), 'en'), $validator->optionsFor($schema, 'ar')])->toBe($before);
})->with([
    'scalar list' => [['vanilla', 'chocolate']],
    'value label map' => [['vanilla' => 'Vanilla', 'chocolate' => 'Chocolate']],
    'bilingual map' => [['vanilla' => ['en' => 'Vanilla', 'ar' => 'فانيليا']]],
]);

it('refuses to hydrate unsupported options rather than silently dropping their data', function (): void {
    $options = [['value' => 'vanilla', 'label' => ['en' => 'Vanilla', 'ar' => 'فانيليا'], 'extra' => ['keep' => true]]];
    $schema = CategoryFieldSchema::factory()->create(['field_type' => 'select', 'options' => $options]);
    Livewire::test(EditCategoryFieldSchema::class, ['record' => $schema->getRouteKey()])
        ->assertHasFormErrors(['options']);
    expect($schema->refresh()->options)->toBe($options);
});

it('exposes category-specific fields and converts major currency input on each admin service form', function (string $locale, ProductType $type): void {
    app()->setLocale($locale);
    $category = Category::factory()->create(['allowed_product_types' => [$type->value]]);
    CategoryFieldSchema::factory()->create([
        'category_id' => $category->id, 'product_type' => $type, 'field_key' => 'flavor', 'field_type' => 'select',
        'field_label' => ['en' => 'Flavor', 'ar' => 'النكهة'], 'is_required' => true,
        'options' => [
            ['value' => 'vanilla', 'label' => ['en' => 'Vanilla', 'ar' => 'فانيليا']],
            ['value' => 'chocolate', 'label' => ['en' => 'Chocolate', 'ar' => 'شوكولاتة']],
        ],
        'validation_rules' => null,
    ]);
    $classType = ucfirst($type->value);
    $edit = "App\\Modules\\Catalog\\Filament\\Resources\\{$classType}ServiceResource\\Pages\\Edit{$classType}Service";
    $service = match ($type) {
        ProductType::Rental => Service::factory()->rental()->create([
            'category_id' => $category->id,
            'category_field_values' => ['flavor' => 'vanilla'],
        ]),
        ProductType::Sale => Service::factory()->sale()->create([
            'category_id' => $category->id,
            'category_field_values' => ['flavor' => 'vanilla'],
        ]),
        ProductType::Digital => Service::factory()->digital()->create([
            'category_id' => $category->id,
            'category_field_values' => ['flavor' => 'vanilla'],
        ]),
    };
    match ($type) {
        ProductType::Rental => $service->rentalDetail()->create(['default_rental_duration_hours' => 4]),
        ProductType::Sale => $service->saleDetail()->create([]),
        ProductType::Digital => $service->digitalDetail()->create(['delivery_method' => 'email']),
    };

    $component = Livewire::test($edit, ['record' => $service->getRouteKey()])
        ->assertFormFieldExists('category_field_values.flavor', fn (Select $field): bool => $field->getOptions() === [
            'vanilla' => $locale === 'ar' ? 'فانيليا' : 'Vanilla',
            'chocolate' => $locale === 'ar' ? 'شوكولاتة' : 'Chocolate',
        ])
        ->assertFormSet(['category_field_values.flavor' => 'vanilla']);
    $component->fillForm(['category_field_values.flavor' => 'chocolate'])->call('save')->assertHasNoFormErrors();
    expect($service->refresh()->category_field_values)->toBe(['flavor' => 'chocolate']);
})->with(['en', 'ar'])->with(ProductType::cases());

it('uses the same styled occasion icon upload on create and edit', function (): void {
    $occasion = Occasion::factory()->create();

    foreach ([
        [CreateOccasion::class, []],
        [EditOccasion::class, ['record' => $occasion->public_id]],
    ] as [$page, $parameters]) {
        Livewire::test($page, $parameters)->assertFormFieldExists('icon_path', fn (FileUpload $field): bool => $field->getDiskName() === 'public'
            && $field->getDirectory() === 'icons/occasions');
    }
});

it('sets the product type and slug when an admin creates a digital service', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $category = Category::factory()->create(['allowed_product_types' => [ProductType::Digital->value]]);

    Livewire::test(CreateDigitalService::class)->fillForm([
        'vendor_profile_id' => $vendor->id,
        'category_id' => $category->id,
        'name' => ['en' => 'Digital Template', 'ar' => 'قالب رقمي'],
        'short_description' => ['en' => 'A template', 'ar' => 'قالب'],
        'base_price_minor' => '250.50',
        'digitalDetail.delivery_method' => 'email',
    ])->call('create')->assertHasNoFormErrors();

    $service = Service::query()->sole();
    expect($service->product_type)->toBe(ProductType::Digital)
        ->and($service->slug)->toStartWith('digital-template-');
});

it('shows a single persisted gallery image once on the admin edit form', function (): void {
    Storage::fake('s3-public');
    $service = Service::factory()->rental()->create();
    $media = $service->addMedia(UploadedFile::fake()->image('single.jpg'))->toMediaCollection('gallery');

    Livewire::test(EditRentalService::class, ['record' => $service->public_id])
        ->assertFormSet(['gallery' => [$media->uuid => $media->uuid]]);
});

it('selects an approved import vendor independently from the admin account', function (ProductType $type): void {
    app()->setLocale('en');
    $vendor = VendorProfile::factory()->approved()->create(['business_name' => ['en' => 'Import Vendor', 'ar' => 'مورد الاستيراد']]);
    VendorProfile::factory()->create(['approval_status' => 'pending', 'business_name' => ['en' => 'Pending Import Vendor', 'ar' => 'مورد معلق']]);
    $classType = ucfirst($type->value);
    $page = "App\\Modules\\Catalog\\Filament\\Pages\\Import{$classType}ServicesPage";
    expect(auth()->user()->vendorProfile)->toBeNull();
    Livewire::test($page)->assertFormFieldExists('vendor_public_id', fn (Select $field): bool => $field->getSearchResults('Import Vendor') === [
        $vendor->public_id => 'Import Vendor',
    ])->fillForm(['vendor_public_id' => $vendor->public_id])->assertFormSet(['vendor_public_id' => $vendor->public_id]);
})->with(ProductType::cases());

it('imports for the selected approved vendor without requiring an admin vendor profile', function (ProductType $type): void {
    app()->setLocale('en');
    Storage::fake('local');
    $vendor = VendorProfile::factory()->approved()->create();
    $classType = ucfirst($type->value);
    $page = "App\\Modules\\Catalog\\Filament\\Pages\\Import{$classType}ServicesPage";
    $actionClass = "App\\Modules\\Catalog\\Application\\Actions\\Import{$classType}ServicesFromExcelAction";
    $import = ExcelImport::factory()->completed()->create(['vendor_profile_id' => $vendor->id, 'product_type' => $type]);
    $action = Mockery::mock($actionClass);
    $action->shouldReceive('execute')->once()->withArgs(fn ($file, $vendorId, $locale): bool => $file instanceof UploadedFile
        && $vendorId === $vendor->id && $locale === 'en')->andReturn($import);
    app()->instance($actionClass, $action);
    Livewire::test($page)->fillForm([
        'vendor_public_id' => $vendor->public_id,
        'file' => UploadedFile::fake()->createWithContent('services.csv', "name_en,name_ar\nCake,كيك\n"),
    ])->call('import')->assertHasNoFormErrors()->assertSet('lastImport.id', $import->id);
})->with(ProductType::cases());

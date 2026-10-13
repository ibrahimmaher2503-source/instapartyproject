<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Services\CategoryFieldValidationService;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Catalog\Filament\Resources\CategoryFieldSchemaResource\Pages\EditCategoryFieldSchema;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        ->assertFormFieldExists('field_label.ar', function (TextInput $field): bool {
            $tab = $field->getContainer()->getParentComponent();

            return $tab instanceof Tab && $tab->getLabel() === 'العربية';
        })
        ->assertDontSee('Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©')
        ->call('save')->assertHasNoFormErrors();
    expect($schema->refresh()->options)->toBe($options);
})->with(['en', 'ar']);

it('rejects duplicate schema option values without overwriting either stored label', function (): void {
    $options = [
        ['value' => 'vanilla', 'label' => ['en' => 'Vanilla', 'ar' => 'فانيليا']],
        ['value' => 'chocolate', 'label' => ['en' => 'Chocolate', 'ar' => 'شوكولاتة']],
    ];
    $schema = CategoryFieldSchema::factory()->create(['field_type' => 'select', 'options' => $options, 'validation_rules' => null]);
    $duplicate = $options;
    $duplicate[1]['value'] = 'vanilla';
    $component = Livewire::test(EditCategoryFieldSchema::class, ['record' => $schema->getRouteKey()])
        ->fillForm(['options' => $duplicate]);
    $firstKey = array_key_first($component->get('data.options'));
    $component->call('save')->assertHasFormErrors(['options.'.$firstKey.'.value' => 'distinct']);
    expect($schema->refresh()->options)->toBe($options);
});

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
    expect(fn () => Livewire::test(EditCategoryFieldSchema::class, ['record' => $schema->getRouteKey()]))
        ->toThrow(ValidationException::class);
    expect($schema->refresh()->options)->toBe($options);
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

it('denies vendor readers and staff with only a different product creation permission at the import boundary', function (string $case, ProductType $type): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo(Permission::findOrCreate('view_any_vendor::profile', 'web'));
    if ($case === 'different_type') {
        $otherType = $type === ProductType::Sale ? ProductType::Rental : ProductType::Sale;
        $staff->givePermissionTo(Permission::findOrCreate("create_{$otherType->value}::service", 'web'));
    }
    $this->actingAs($staff);
    $classType = ucfirst($type->value);
    $page = "App\\Modules\\Catalog\\Filament\\Pages\\Import{$classType}ServicesPage";
    $actionClass = "App\\Modules\\Catalog\\Application\\Actions\\Import{$classType}ServicesFromExcelAction";
    $action = Mockery::mock($actionClass);
    $action->shouldNotReceive('execute');
    expect($page::canAccess())->toBeFalse();
    expect(fn () => (new $page)->import($action))->toThrow(HttpException::class);
    expect(ExcelImport::query()->count())->toBe(0);
})->with(['read_only', 'different_type'])->with(ProductType::cases());

it('allows staff to import only the product type they are explicitly permitted to create', function (ProductType $type): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo(Permission::findOrCreate('view_any_vendor::profile', 'web'));
    $staff->givePermissionTo(Permission::findOrCreate("create_{$type->value}::service", 'web'));
    $this->actingAs($staff);
    $classType = ucfirst($type->value);
    $page = "App\\Modules\\Catalog\\Filament\\Pages\\Import{$classType}ServicesPage";
    expect($page::canAccess())->toBeTrue();
    $staff->revokePermissionTo(Permission::findByName('view_any_vendor::profile', 'web'));
    $staff->unsetRelation('permissions');
    expect($page::canAccess())->toBeFalse();
})->with(ProductType::cases());

it('keeps approved vendor own-store imports authorized without granting admin import authority', function (ProductType $type): void {
    $vendor = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType($type)->create(['vendor_profile_id' => $vendor->id]);
    $classType = ucfirst($type->value);
    $requestClass = "App\\Modules\\Catalog\\Http\\Requests\\Import{$classType}ServicesRequest";
    $request = new $requestClass;
    $request->setUserResolver(fn () => $vendor->user);
    $request->merge(['store_id' => $vendor->public_id]);
    expect($request->authorize())->toBeTrue()->and($request->vendorProfile()->id)->toBe($vendor->id);
    $request->merge(['store_id' => (string) Str::ulid()]);
    expect(fn () => $request->vendorProfile())->toThrow(HttpException::class);
    $this->actingAs($vendor->user);
    $adminPage = "App\\Modules\\Catalog\\Filament\\Pages\\Import{$classType}ServicesPage";
    expect($adminPage::canAccess())->toBeFalse();
})->with(ProductType::cases());

it('hides the service-list import action from staff without its exact creation permission', function (ProductType $type): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo(Permission::findOrCreate("view_any_{$type->value}::service", 'web'));
    $otherType = $type === ProductType::Sale ? ProductType::Digital : ProductType::Sale;
    $staff->givePermissionTo(Permission::findOrCreate("create_{$otherType->value}::service", 'web'));
    $this->actingAs($staff);
    $classType = ucfirst($type->value);
    $list = "App\\Modules\\Catalog\\Filament\\Resources\\{$classType}ServiceResource\\Pages\\List{$classType}Services";
    $component = Livewire::test($list)->assertTableActionHidden("import{$classType}Services");
    $callback = $component->instance()->getTable()->getAction("import{$classType}Services")->getActionFunction();
    $actionClass = "App\\Modules\\Catalog\\Application\\Actions\\Import{$classType}ServicesFromExcelAction";
    $action = Mockery::mock($actionClass);
    $action->shouldNotReceive('execute');
    expect(fn () => $callback([], $action))->toThrow(HttpException::class);
    $staff->givePermissionTo(Permission::findOrCreate("create_{$type->value}::service", 'web'));
    $staff->unsetRelation('permissions');
    Livewire::test($list)->assertTableActionVisible("import{$classType}Services");
})->with([ProductType::Sale, ProductType::Digital]);

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

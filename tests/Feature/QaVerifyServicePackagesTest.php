<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Catalog\Application\Actions\ListPublicServicePackagesAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceDigitalDetail;
use App\Modules\Catalog\Domain\Models\ServicePackage;
use App\Modules\Catalog\Domain\Models\ServicePackageComponent;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Catalog\Domain\Policies\ServicePackagePolicy;
use App\Modules\Catalog\Filament\Resources\ServicePackageResource\Pages\CreateServicePackage;
use App\Modules\Catalog\Filament\Resources\ServicePackageResource\Pages\EditServicePackage;
use App\Modules\Catalog\Infrastructure\Repositories\EloquentCatalogServiceReader;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('binds package resource authorization to the existing per-service permissions', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('view_rental::service', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('create_rental::service', 'web'));
    $service = makePackageService(ProductType::Rental);
    $package = createServicePackage($service);

    expect(Gate::getPolicyFor(ServicePackage::class))->toBeInstanceOf(ServicePackagePolicy::class)
        ->and($user->can('viewAny', ServicePackage::class))->toBeTrue()
        ->and($user->can('create', ServicePackage::class))->toBeTrue()
        ->and($user->can('view', $package))->toBeTrue()
        ->and($user->can('update', $package))->toBeFalse();
});

it('hides packages immediately when their published service owner is no longer eligible', function (ProductType $type, string $state): void {
    $service = makePackageService($type);
    $package = createServicePackage($service);
    match ($state) {
        'suspended' => $service->vendor->forceFill(['approval_status' => 'suspended'])->saveQuietly(),
        'type-revoked' => $service->vendor->approvedTypes()->update(['revoked_at' => now()]),
        'vendor-deleted' => $service->vendor->forceFill(['deleted_at' => now()])->saveQuietly(),
        'service-deleted' => $service->forceFill(['deleted_at' => now()])->saveQuietly(),
    };
    expect($service->fresh()->status->getValue())->toBe('published');
    $reader = app(EloquentCatalogServiceReader::class);
    expect(app(ListPublicServicePackagesAction::class)->execute($service->public_id))->toHaveCount(0)
        ->and($reader->findActivePackageForService($service->public_id, $package->public_id))->toBeNull()
        ->and($reader->findEquivalentActivePackageForService($service->public_id, $package->code, $package->price_minor, $package->currency))->toBeNull();
    $response = $this->getJson('/api/v1/customer/services/'.$service->public_id);
    if ($state === 'service-deleted') {
        $response->assertNotFound();
    } else {
        $response->assertOk()->assertJsonCount(0, 'data.packages');
    }
})->with(ProductType::cases())->with(['suspended', 'type-revoked', 'vendor-deleted', 'service-deleted']);

it('rolls back the package and earlier components when a component save fails', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('create_sale::service', 'web'));
    $this->actingAs($admin);
    $service = makePackageService(ProductType::Sale);
    ServicePackageComponent::creating(function (ServicePackageComponent $component): void {
        if ($component->getTranslation('name', 'en') === 'Rejected component') {
            throw new RuntimeException('Component persistence failed');
        }
    });

    $component = Livewire::test(CreateServicePackage::class)->fillForm([
        'service_id' => $service->id,
        'code' => 'qa-atomic-package',
        'name' => ['en' => 'Atomic Bundle', 'ar' => 'باقة ذرية'],
        'description' => ['en' => 'Bundle details', 'ar' => 'تفاصيل الباقة'],
        'price_minor' => 3000,
        'is_active' => true,
        'components' => [
            ['name' => ['en' => 'First component', 'ar' => 'المكون الأول'], 'description' => ['en' => 'First', 'ar' => 'الأول'], 'quantity' => 1, 'sort_order' => 0],
            ['name' => ['en' => 'Rejected component', 'ar' => 'المكون المرفوض'], 'description' => ['en' => 'Second', 'ar' => 'الثاني'], 'quantity' => 1, 'sort_order' => 1],
        ],
    ]);
    expect(fn () => $component->call('create'))->toThrow(RuntimeException::class, 'Component persistence failed');
    expect(ServicePackage::query()->where('code', 'qa-atomic-package')->exists())->toBeFalse()
        ->and(ServicePackageComponent::query()->count())->toBe(0);
});

function makePackageService(ProductType $type): Service
{
    $service = match ($type) {
        ProductType::Rental => Service::factory()->rental(),
        ProductType::Sale => Service::factory()->sale(),
        ProductType::Digital => Service::factory()->digital(),
    };

    $service = $service->published()->create();
    $service->vendor->approvedTypes()->create([
        'product_type' => $type,
        'approved_at' => now(),
    ]);

    match ($type) {
        ProductType::Rental => ServiceRentalDetail::factory()->create(['service_id' => $service->id]),
        ProductType::Sale => ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 20, 'customization_fields' => []]),
        ProductType::Digital => ServiceDigitalDetail::factory()->create(['service_id' => $service->id, 'delivery_method' => 'email']),
    };

    return $service;
}

function createServicePackage(Service $service, array $overrides = []): ServicePackage
{
    $package = $service->packages()->create(array_merge([
        'public_id' => (string) Str::ulid(),
        'code' => 'qa-package',
        'name' => ['en' => 'Garden Bundle', 'ar' => 'باقة الحديقة'],
        'description' => ['en' => 'A bilingual package.', 'ar' => 'باقة بلغتين.'],
        'price_minor' => 2450,
        'currency' => $service->base_price_currency,
        'is_active' => true,
    ], $overrides));

    $package->components()->create([
        'public_id' => (string) Str::ulid(),
        'name' => ['en' => 'Table pieces', 'ar' => 'قطع الطاولة'],
        'description' => ['en' => 'Set of pieces', 'ar' => 'مجموعة قطع'],
        'quantity' => 3,
        'sort_order' => 0,
    ]);

    return $package;
}

it('lets an admin create bilingual service packages for approved services of every product type', function (ProductType $type): void {
    Storage::fake('s3-public');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any', 'view', 'create', 'update', 'delete'] as $operation) {
        foreach (ProductType::cases() as $permissionType) {
            $admin->givePermissionTo(Permission::findOrCreate($operation.'_'.$permissionType->value.'::service', 'web'));
        }
    }
    $this->actingAs($admin);
    $service = makePackageService($type);

    Livewire::test(CreateServicePackage::class)
        ->assertFormFieldExists('service_id', fn (Select $field): bool => $field->getSearchResults('') !== [])
        ->assertFormFieldExists('components', fn (Repeater $field): bool => $field->isReorderable())
        ->fillForm([
            'service_id' => $service->id,
            'code' => 'qa-package-'.strtolower($type->value),
            'name' => ['en' => 'Wedding Bundle', 'ar' => 'باقة زفاف'],
            'description' => ['en' => 'English details', 'ar' => 'تفاصيل عربية'],
            'price_minor' => 12345,
            'is_active' => true,
            'components' => [[
                'name' => ['en' => 'Chair', 'ar' => 'كرسي'],
                'description' => ['en' => 'One chair', 'ar' => 'كرسي واحد'],
                'quantity' => 4,
                'sort_order' => 0,
            ]],
            'gallery' => [UploadedFile::fake()->image('package-'.strtolower($type->value).'.png')],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $package = ServicePackage::query()->where('code', 'qa-package-'.strtolower($type->value))->with('components')->sole();
    expect($package->service_id)->toBe($service->id)
        ->and($package->currency)->toBe($service->base_price_currency)
        ->and($package->price_minor)->toBe(12345)
        ->and($package->getTranslations('name'))->toBe(['en' => 'Wedding Bundle', 'ar' => 'باقة زفاف'])
        ->and($package->components->sole()->quantity)->toBe(4)
        ->and($package->components->sole()->getTranslations('name'))->toBe(['en' => 'Chair', 'ar' => 'كرسي'])
        ->and($package->getMedia('gallery'))->toHaveCount(1)
        ->and($package->getMedia('gallery')->sole()->disk)->toBe('s3-public');
})->with(ProductType::cases());

it('shows required package fields for both locales and creates a valid package', function (string $locale): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('create_sale::service', 'web'));
    $this->actingAs($admin);
    $service = makePackageService(ProductType::Sale);

    Livewire::test(CreateServicePackage::class)
        ->call('create')
        ->assertHasFormErrors([
            'service_id' => 'required', 'code' => 'required', 'price_minor' => 'required',
            'name.en' => 'required', 'name.ar' => 'required',
            'description.en' => 'required', 'description.ar' => 'required',
        ])
        ->fillForm([
            'service_id' => $service->id,
            'code' => 'qa-empty-validation-'.$locale,
            'name' => ['en' => 'Wedding Bundle', 'ar' => 'باقة زفاف'],
            'description' => ['en' => 'English details', 'ar' => 'تفاصيل عربية'],
            'price_minor' => 12345,
            'components' => [[
                'name' => ['en' => 'Chair', 'ar' => 'كرسي'],
                'description' => ['en' => 'One chair', 'ar' => 'كرسي واحد'],
                'quantity' => 1,
                'sort_order' => 0,
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ServicePackage::query()->where('code', 'qa-empty-validation-'.$locale)->exists())->toBeTrue();
})->with(['en', 'ar']);

it('edits a package and its component values without changing its service owner', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any', 'view', 'create', 'update', 'delete'] as $operation) {
        foreach (ProductType::cases() as $type) {
            $admin->givePermissionTo(Permission::findOrCreate($operation.'_'.$type->value.'::service', 'web'));
        }
    }
    $this->actingAs($admin);
    $service = makePackageService(ProductType::Sale);
    $otherService = makePackageService(ProductType::Sale);
    $package = createServicePackage($service);

    Livewire::test(EditServicePackage::class, ['record' => $package->public_id])
        ->fillForm([
            'service_id' => $otherService->id,
            'code' => 'qa-package',
            'name' => ['en' => 'Revised Bundle', 'ar' => 'باقة معدلة'],
            'description' => ['en' => 'Revised description', 'ar' => 'وصف معدل'],
            'price_minor' => 3450,
            'is_active' => false,
            'components' => [[
                'name' => ['en' => 'Revised piece', 'ar' => 'قطعة معدلة'],
                'description' => ['en' => 'Two pieces', 'ar' => 'قطعتان'],
                'quantity' => 2,
                'sort_order' => 0,
            ]],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $package = $package->fresh()->load('components');
    expect($package->service_id)->toBe($service->id)
        ->and($package->price_minor)->toBe(3450)
        ->and($package->is_active)->toBeFalse()
        ->and($package->getTranslations('name'))->toBe(['en' => 'Revised Bundle', 'ar' => 'باقة معدلة'])
        ->and($package->components->sole()->quantity)->toBe(2)
        ->and($package->components->sole()->getTranslations('name'))->toBe(['en' => 'Revised piece', 'ar' => 'قطعة معدلة']);
});

it('allows an update-only catalog admin to edit an existing package for its still-eligible service', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin', 'web'));
    foreach (['view_any', 'view', 'update'] as $operation) {
        $admin->givePermissionTo(Permission::findOrCreate($operation.'_sale::service', 'web'));
    }
    $this->actingAs($admin);
    $service = makePackageService(ProductType::Sale);
    $package = createServicePackage($service);

    expect($admin->can('create_sale::service', 'web'))->toBeFalse();

    Livewire::test(EditServicePackage::class, ['record' => $package->public_id])
        ->fillForm([
            'service_id' => $service->id,
            'code' => $package->code,
            'name' => ['en' => 'Updated by editor', 'ar' => 'تحديث بواسطة المحرر'],
            'description' => ['en' => 'Updated details', 'ar' => 'تفاصيل محدثة'],
            'price_minor' => 3000,
            'is_active' => true,
            'components' => [[
                'name' => ['en' => 'Chair', 'ar' => 'كرسي'],
                'description' => ['en' => 'One chair', 'ar' => 'كرسي واحد'],
                'quantity' => 1,
                'sort_order' => 0,
            ]],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($package->fresh()->price_minor)->toBe(3000)
        ->and($package->fresh()->service_id)->toBe($service->id);
});

it('limits package service choices to the admin product-type grants and validates tampered IDs', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin', 'web'));
    foreach (['view_any_sale::service', 'view_sale::service', 'create_sale::service'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($admin);
    $sale = makePackageService(ProductType::Sale);
    $rental = makePackageService(ProductType::Rental);

    Livewire::test(CreateServicePackage::class)
        ->assertFormFieldExists('service_id', fn (Select $field): bool => array_keys($field->getSearchResults('')) === [$sale->id])
        ->fillForm([
            'service_id' => $rental->id,
            'code' => 'wrong-product-type',
            'name' => ['en' => 'Invalid', 'ar' => 'غير صالح'],
            'description' => ['en' => 'Invalid', 'ar' => 'غير صالح'],
            'price_minor' => 100,
            'is_active' => true,
            'components' => [[
                'name' => ['en' => 'Piece', 'ar' => 'قطعة'],
                'description' => ['en' => 'Piece', 'ar' => 'قطعة'],
                'quantity' => 1,
                'sort_order' => 0,
            ]],
        ])
        ->call('create')
        ->assertHasFormErrors(['service_id']);

    expect(ServicePackage::query()->where('code', 'wrong-product-type')->exists())->toBeFalse();
});

it('requires a package delete grant separately from update permission', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin', 'web'));
    foreach (['view_any', 'view', 'update'] as $operation) {
        $admin->givePermissionTo(Permission::findOrCreate($operation.'_sale::service', 'web'));
    }
    $this->actingAs($admin);
    $service = makePackageService(ProductType::Sale);
    $package = createServicePackage($service);
    $policy = app(ServicePackagePolicy::class);

    expect($policy->update($admin, $package))->toBeTrue()
        ->and($policy->delete($admin, $package))->toBeFalse();

    Livewire::test(EditServicePackage::class, ['record' => $package->public_id])
        ->assertActionHidden('delete');

    $admin->givePermissionTo(Permission::findOrCreate('delete_sale::service', 'web'));

    expect($policy->delete($admin, $package))->toBeTrue();

    Livewire::test(EditServicePackage::class, ['record' => $package->public_id])
        ->assertActionVisible('delete');
});

it('publishes only active package choices in the requested locale', function (ProductType $type, string $locale): void {
    app()->setLocale($locale);
    $service = makePackageService($type);
    $active = createServicePackage($service);
    createServicePackage($service, [
        'code' => 'qa-inactive-package',
        'is_active' => false,
        'name' => ['en' => 'Inactive bundle', 'ar' => 'باقة غير نشطة'],
    ]);

    $this->getJson('/api/v1/customer/services/'.$service->public_id, ['Accept-Language' => $locale])
        ->assertOk()
        ->assertJsonPath('data.packages.0.public_id', $active->public_id)
        ->assertJsonPath('data.packages.0.name', $active->getTranslation('name', $locale))
        ->assertJsonPath('data.packages.0.components.0.quantity', 3)
        ->assertJsonCount(1, 'data.packages');

    $this->actingAs(User::factory()->asCustomer()->create())
        ->get('/'.$locale.'/wizard?service='.$service->public_id)
        ->assertOk()
        ->assertSee($active->getTranslation('name', $locale), false)
        ->assertDontSee('Inactive bundle', false)
        ->assertDontSee('باقة غير نشطة', false);
})->with(ProductType::cases())->with(['en', 'ar']);

it('uses a selected package price and freezes its bilingual contents in the booking history', function (ProductType $type, string $locale): void {
    Storage::fake('s3-public');
    app()->setLocale($locale);
    $customer = User::factory()->asCustomer()->create();
    $service = makePackageService($type);
    $quantity = $type === ProductType::Rental ? 1 : 2;
    $package = createServicePackage($service, [
        'name' => ['en' => 'Premium Set', 'ar' => 'المجموعة المميزة'],
        'price_minor' => 7800,
    ]);
    $media = $package->addMedia(UploadedFile::fake()->image('history-package.png'))->toMediaCollection('gallery');
    $snapshotName = ['en' => 'Premium Set', 'ar' => 'المجموعة المميزة'];
    $occasion = Occasion::factory()->create(['is_active' => true]);
    $service->category->occasions()->attach($occasion->id);
    $city = City::factory()->create(['is_active' => true]);

    $this->actingAs($customer)->post('/'.$locale.'/cart/setup', [
        'service_id' => $service->public_id,
        'package_public_id' => $package->public_id,
        'occasion' => $occasion->code,
        'event_starts_at' => now()->addDays(2)->startOfHour()->toDateTimeString(),
        'event_ends_at' => now()->addDays(2)->startOfHour()->addHours(3)->toDateTimeString(),
        'address' => [
            'city_id' => $city->public_id,
            'address_line' => '21 Package Street',
            'recipient_name' => 'Package Customer',
            'recipient_phone_e164' => '+201001234567',
        ],
        'quantity' => $quantity,
        'timezone' => 'Africa/Cairo',
        'unit_price_minor' => 1,
    ])->assertRedirect('/'.$locale.'/cart');

    $booking = Booking::query()->where('customer_id', $customer->id)->sole();
    $item = $booking->items()->sole();
    expect($item->unit_price_minor)->toBe(7800)
        ->and($item->line_total_minor)->toBe(7800 * $quantity)
        ->and($item->name_snapshot)->toBe(['en' => 'Premium Set', 'ar' => 'المجموعة المميزة'])
        ->and($item->type_snapshot['service_package']['public_id'])->toBe($package->public_id)
        ->and($item->type_snapshot['service_package']['components'][0]['quantity'])->toBe(3)
        ->and($item->type_snapshot['service_package']['media'][0]['public_id'])->toBe($media->public_id);

    $this->getJson('/api/v1/customer/bookings/current-draft', ['Accept-Language' => $locale])
        ->assertOk()
        ->assertJsonPath('data.vendors.0.items.0.service_package.public_id', $package->public_id)
        ->assertJsonPath('data.vendors.0.items.0.service_package.name', $snapshotName)
        ->assertJsonPath('data.vendors.0.items.0.service_package.components.0.quantity', 3);

    $package->update(['name' => ['en' => 'Changed after booking', 'ar' => 'تغيرت بعد الحجز'], 'price_minor' => 999999, 'is_active' => false]);
    $package->components()->update(['name' => ['en' => 'Edited', 'ar' => 'معدل']]);
    $package->delete();

    expect($item->fresh()->type_snapshot['service_package']['name'])->toBe($snapshotName)
        ->and($item->fresh()->type_snapshot['service_package']['price_minor'])->toBe(7800)
        ->and($item->fresh()->type_snapshot['service_package']['components'][0]['name'])->toBe(['en' => 'Table pieces', 'ar' => 'قطع الطاولة'])
        ->and($item->fresh()->type_snapshot['service_package']['media'][0]['public_id'])->toBe($media->public_id);

    $this->get('/'.$locale.'/cart')
        ->assertOk()
        ->assertSee($snapshotName[$locale], false)
        ->assertDontSee($locale === 'en' ? 'Changed after booking' : 'تغيرت بعد الحجز', false)
        ->assertSee($locale === 'en' ? 'Table pieces' : 'قطع الطاولة', false)
        ->assertSee('3', false)
        ->assertSee($media->getUrl('thumb'), false);

    $this->actingAs($customer, 'web')
        ->get(route('storefront.account.bookings.show', [$locale, $booking->public_id]))
        ->assertOk()
        ->assertSee($snapshotName[$locale], false)
        ->assertSee($locale === 'en' ? 'Table pieces' : 'قطع الطاولة', false)
        ->assertSee($media->getUrl('thumb'), false);

    $this->get(route('storefront.account.bookings.invoice', ['locale' => $locale, 'bookingPublicId' => $booking->public_id]))
        ->assertOk()
        ->assertSee($snapshotName[$locale], false)
        ->assertSee($locale === 'en' ? 'Table pieces' : 'قطع الطاولة', false)
        ->assertSee($media->getUrl('thumb'), false);
})->with(ProductType::cases())->with(['en', 'ar']);

it('rejects a package from another service or one that is inactive without creating a booking', function (string $state): void {
    $customer = User::factory()->asCustomer()->create();
    $service = makePackageService(ProductType::Sale);
    $otherService = makePackageService(ProductType::Sale);
    $package = createServicePackage($state === 'inactive' ? $service : $otherService, $state === 'inactive' ? ['is_active' => false] : []);
    $occasion = Occasion::factory()->create(['is_active' => true]);
    $service->category->occasions()->attach($occasion->id);
    $city = City::factory()->create(['is_active' => true]);

    $this->actingAs($customer)
        ->from('/en/wizard?service='.$service->public_id)
        ->post('/en/cart/setup', [
            'service_id' => $service->public_id,
            'package_public_id' => $package->public_id,
            'occasion' => $occasion->code,
            'event_starts_at' => now()->addDays(2)->startOfHour()->toDateTimeString(),
            'address' => [
                'city_id' => $city->public_id,
                'address_line' => '21 Package Street',
                'recipient_name' => 'Package Customer',
                'recipient_phone_e164' => '+201001234567',
            ],
            'timezone' => 'Africa/Cairo',
        ])
        ->assertRedirect('/en/wizard?service='.$service->public_id)
        ->assertSessionHasErrors('package_public_id');

    expect(Booking::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
})->with(['cross-service', 'inactive']);

it('finds an exact active package by service code and price for replacement matching', function (): void {
    $sourceService = makePackageService(ProductType::Sale);
    $targetService = Service::factory()->sale()->published()->create([
        'vendor_profile_id' => $sourceService->vendor_profile_id,
        'category_id' => $sourceService->category_id,
        'base_price_minor' => 999999,
        'base_price_currency' => $sourceService->base_price_currency,
    ]);
    ServiceSaleDetail::factory()->create(['service_id' => $targetService->id, 'stock_quantity' => 10, 'customization_fields' => []]);
    $package = createServicePackage($targetService, [
        'code' => 'matching-code',
        'price_minor' => 7800,
    ]);
    $otherPrice = createServicePackage($targetService, [
        'code' => 'different-price',
        'price_minor' => 7801,
    ]);
    $reader = app(EloquentCatalogServiceReader::class);

    $replacements = $reader->findEligibleReplacementServicesForPackage(
        $sourceService->vendor_profile_id,
        $sourceService->category_id,
        ProductType::Sale,
        2,
        null,
        null,
    );
    $match = $reader->findEquivalentActivePackageForService($targetService->public_id, 'matching-code', 7800, $targetService->base_price_currency);

    expect(collect($replacements)->pluck('publicId')->all())->toContain($targetService->public_id)
        ->and($match?->publicId)->toBe($package->public_id)
        ->and($reader->findEquivalentActivePackageForService($targetService->public_id, 'matching-code', 7801, $targetService->base_price_currency))->toBeNull()
        ->and($reader->findEquivalentActivePackageForService($targetService->public_id, $otherPrice->code, 7800, $targetService->base_price_currency))->toBeNull();
});

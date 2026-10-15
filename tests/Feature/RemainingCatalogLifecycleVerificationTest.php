<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Application\Actions\ArchiveServiceAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\States\ServiceStatus\ArchivedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\ChangesRequestedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\DraftState;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\RejectedState;
use App\Modules\Catalog\Filament\Actions\BulkApproveServicesAction;
use App\Modules\Catalog\Filament\Actions\BulkArchiveServicesAction;
use App\Modules\Catalog\Filament\Actions\BulkRejectServicesAction;
use App\Modules\Catalog\Filament\Resources\DigitalServiceResource\Pages\ListDigitalServices;
use App\Modules\Catalog\Filament\Resources\RentalServiceResource\Pages\ListRentalServices;
use App\Modules\Catalog\Filament\Resources\SaleServiceResource\Pages\ListSaleServices;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorDigitalServiceResource\Pages\ListVendorDigitalServices;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource\Pages\EditVendorRentalService;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource\Pages\ListVendorRentalServices;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource\Pages\ListVendorSaleServices;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('clones through the vendor list, edits the draft clone, and leaves the published source unchanged', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $vendor = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType(ProductType::Rental)->create(['vendor_profile_id' => $vendor->id]);
    $vendor->user->givePermissionTo(Permission::findOrCreate('service.update.rental.own', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($vendor->user, 'vendor');
    Storage::fake('public');

    $root = Category::factory()->create(['parent_id' => null, 'allowed_product_types' => ['rental']]);
    $child = Category::factory()->create(['parent_id' => $root->id, 'allowed_product_types' => ['rental']]);
    $source = Service::factory()->rental()->published()->create([
        'vendor_profile_id' => $vendor->id,
        'category_id' => $child->id,
        'name' => ['en' => 'Lifecycle source service', 'ar' => 'خدمة مصدر دورة الحياة'],
        'base_price_minor' => 42000,
    ]);
    ServiceRentalDetail::factory()->create(['service_id' => $source->id, 'default_rental_duration_hours' => 6]);

    $list = Livewire::test(ListVendorRentalServices::class)->assertCanSeeTableRecords([$source]);
    $list->callTableAction('clone', $source);
    $clone = Service::query()->where('vendor_profile_id', $vendor->id)->where('id', '!=', $source->id)->sole();
    expect($clone->status)->toBeInstanceOf(DraftState::class);

    Livewire::test(EditVendorRentalService::class, ['record' => $clone->public_id])
        ->fillForm([
            'name' => ['en' => 'Edited clone service', 'ar' => 'خدمة نسخة معدلة'],
            'long_description' => ['en' => 'Edited description.', 'ar' => 'وصف معدل.'],
            'main_category_id' => $root->id,
            'category_id' => $child->id,
            'base_price_minor' => 510,
            'rentalDetail' => ['default_rental_duration_hours' => 8],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($source->fresh()->getTranslations('name'))->toBe(['en' => 'Lifecycle source service', 'ar' => 'خدمة مصدر دورة الحياة'])
        ->and($source->fresh()->base_price_minor)->toBe(42000)
        ->and($source->rentalDetail->fresh()->default_rental_duration_hours)->toBe(6)
        ->and($clone->fresh()->getTranslations('name'))->toBe(['en' => 'Edited clone service', 'ar' => 'خدمة نسخة معدلة'])
        ->and($clone->fresh()->base_price_minor)->toBe(51000)
        ->and($clone->rentalDetail->fresh()->default_rental_duration_hours)->toBe(8)
        ->and($clone->fresh()->status)->toBeInstanceOf(DraftState::class);
});

it('archives published services from the vendor table once, hiding repeat action and removing public availability', function (ProductType $type): void {
    config(['catalog.digital_services_enabled' => true]);
    $vendor = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType($type)->create(['vendor_profile_id' => $vendor->id]);
    $vendor->user->givePermissionTo(Permission::findOrCreate("service.update.{$type->value}.own", 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($vendor->user, 'vendor');
    Storage::fake('public');

    $factory = match ($type) {
        ProductType::Rental => Service::factory()->rental(),
        ProductType::Sale => Service::factory()->sale(),
        ProductType::Digital => Service::factory()->digital(),
    };
    $service = $factory->published()->create(['vendor_profile_id' => $vendor->id]);
    config(['auth.defaults.guard' => 'web']);
    $bookingVendor = BookingVendor::factory()->create(['vendor_profile_id' => $vendor->id]);
    $item = BookingItem::factory()->create([
        'booking_vendor_id' => $bookingVendor->id,
        'service_id' => $service->id,
        'product_type' => $type,
        'name_snapshot' => ['en' => 'Archived history label', 'ar' => 'اسم محفوظ للأرشيف'],
        'type_snapshot' => ['category_field_values' => ['capacity' => 31]],
    ]);
    $listPage = match ($type) {
        ProductType::Rental => ListVendorRentalServices::class,
        ProductType::Sale => ListVendorSaleServices::class,
        ProductType::Digital => ListVendorDigitalServices::class,
    };

    config(['auth.defaults.guard' => 'vendor']);
    $this->actingAs($vendor->user, 'vendor');
    Livewire::test($listPage)->callTableAction('archive', $service);

    expect($service->fresh()->status)->toBeInstanceOf(ArchivedState::class)
        ->and($item->fresh()->name_snapshot)->toBe(['en' => 'Archived history label', 'ar' => 'اسم محفوظ للأرشيف'])
        ->and($item->fresh()->type_snapshot)->toBe(['category_field_values' => ['capacity' => 31]])
        ->and($item->fresh()->service->public_id)->toBe($service->public_id);
    auth()->guard('vendor')->logout();
    config(['auth.defaults.guard' => 'web']);
    $this->get('/en/services/'.$service->public_id)->assertNotFound();
    config(['auth.defaults.guard' => 'vendor']);
    $this->actingAs($vendor->user, 'vendor');
    Livewire::test($listPage)->assertTableActionHidden('archive', $service->fresh());
})->with([
    'rental' => ProductType::Rental,
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
]);

it('filters admin service tables by product type and status after moderation while retaining unrelated rows', function (): void {
    config(['catalog.digital_services_enabled' => true]);
    $admin = User::factory()->asAdmin()->create();
    $admin->givePermissionTo([
        Permission::findOrCreate('reject_service', 'web'),
        Permission::findOrCreate('request_service_edits', 'web'),
    ]);
    foreach (ProductType::cases() as $type) {
        $admin->givePermissionTo([
            Permission::findOrCreate("view_any_{$type->value}::service", 'web'),
            Permission::findOrCreate("publish_{$type->value}_service", 'web'),
        ]);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($admin);

    $vendor = VendorProfile::factory()->approved()->create();
    $category = Category::factory()->create(['allowed_product_types' => ['rental', 'sale', 'digital']]);
    Storage::fake('public');
    $pending = Service::factory()->rental()->pendingReview()->create([
        'vendor_profile_id' => $vendor->id,
        'category_id' => $category->id,
        'name' => ['en' => 'Remaining pending rental', 'ar' => 'خدمة إيجار معلقة'],
    ]);
    $publishedSale = Service::factory()->sale()->published()->create([
        'vendor_profile_id' => $vendor->id,
        'category_id' => $category->id,
        'name' => ['en' => 'Unrelated published sale', 'ar' => 'بيع منشور غير مرتبط'],
    ]);
    $pendingSale = Service::factory()->sale()->pendingReview()->create([
        'vendor_profile_id' => $vendor->id,
        'category_id' => $category->id,
        'name' => ['en' => 'Pending sale moderation', 'ar' => 'بيع معلق للمراجعة'],
    ]);
    $pendingDigital = Service::factory()->digital()->pendingReview()->create([
        'vendor_profile_id' => $vendor->id,
        'category_id' => $category->id,
        'name' => ['en' => 'Pending digital moderation', 'ar' => 'رقمي معلق للمراجعة'],
    ]);

    Livewire::test(ListRentalServices::class)
        ->searchTable('Remaining pending rental')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$publishedSale])
        ->assertTableActionVisible('approvePublish', $pending)
        ->assertTableActionVisible('rejectService', $pending)
        ->assertTableActionVisible('requestChanges', $pending);
    Livewire::test(ListSaleServices::class)
        ->searchTable('Unrelated published sale')
        ->assertCanSeeTableRecords([$publishedSale])
        ->assertCanNotSeeTableRecords([$pending]);
    Livewire::test(ListSaleServices::class)
        ->searchTable('Pending sale moderation')
        ->assertCanSeeTableRecords([$pendingSale])
        ->assertTableActionVisible('approvePublish', $pendingSale)
        ->assertTableActionVisible('rejectService', $pendingSale)
        ->assertTableActionVisible('requestChanges', $pendingSale);
    Livewire::test(ListDigitalServices::class)
        ->searchTable('Pending digital moderation')
        ->assertCanSeeTableRecords([$pendingDigital])
        ->assertTableActionVisible('approvePublish', $pendingDigital)
        ->assertTableActionVisible('rejectService', $pendingDigital)
        ->assertTableActionVisible('requestChanges', $pendingDigital);
});

it('executes the admin sale rejection form with bilingual reason data', function (): void {
    $admin = User::factory()->asAdmin()->create();
    foreach (['view_any_sale::service', 'publish_sale_service', 'view_any_digital::service', 'publish_digital_service'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($admin);
    $vendor = VendorProfile::factory()->approved()->create();
    $category = Category::factory()->create(['allowed_product_types' => ['sale']]);
    $sale = Service::factory()->sale()->pendingReview()->create([
        'vendor_profile_id' => $vendor->id,
        'category_id' => $category->id,
    ]);
    $digital = Service::factory()->digital()->pendingReview()->create([
        'vendor_profile_id' => $vendor->id,
        'category_id' => $category->id,
    ]);

    Livewire::test(ListSaleServices::class)
        ->assertTableActionVisible('approvePublish', $sale)
        ->assertTableActionHidden('rejectService', $sale)
        ->assertTableActionHidden('requestChanges', $sale);
    Livewire::test(ListDigitalServices::class)
        ->assertTableActionHidden('rejectService', $digital)
        ->assertTableActionHidden('requestChanges', $digital);

    $admin->givePermissionTo([
        Permission::findOrCreate('reject_service', 'web'),
        Permission::findOrCreate('request_service_edits', 'web'),
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $component = Livewire::test(ListSaleServices::class)
        ->assertTableActionVisible('rejectService', $sale)
        ->mountTableAction('rejectService', $sale)
        ->setTableActionData(['reason' => ['en' => 'Rejected in test.', 'ar' => 'مرفوض للاختبار.']])
        ->assertTableActionDataSet(['reason' => ['en' => 'Rejected in test.', 'ar' => 'مرفوض للاختبار.']])
        ->call('callMountedTableAction');

    expect($component->errors()->toArray())->toBeEmpty();

    expect($sale->fresh()->status)->toBeInstanceOf(RejectedState::class)
        ->and($sale->fresh()->getTranslations('moderation_notes'))->toBe(['en' => 'Rejected in test.', 'ar' => 'مرفوض للاختبار.'])
        ->and($sale->fresh()->moderated_by)->toBe($admin->id);

    $changeComponent = Livewire::test(ListDigitalServices::class)
        ->assertTableActionVisible('requestChanges', $digital)
        ->mountTableAction('requestChanges', $digital);
    $itemKey = array_key_first($changeComponent->instance()->mountedTableActionsData[0]['items']);
    $changeComponent
        ->setTableActionData([
            'items' => [$itemKey => [
                'field_path' => 'short_description',
                'requested_change_en' => 'Clarify the digital delivery steps.',
                'requested_change_ar' => 'وضّح خطوات التسليم الرقمي.',
            ]],
        ])
        ->call('callMountedTableAction');

    expect($changeComponent->errors()->toArray())->toBe([])
        ->and($digital->fresh()->status)->toBeInstanceOf(ChangesRequestedState::class)
        ->and($digital->changeRequests()->where('status', 'open')->count())->toBe(1)
        ->and($digital->fresh()->moderated_by)->toBe($admin->id);
});

it('executes supported admin bulk moderation and archive actions without changing unrelated rows', function (): void {
    config(['catalog.digital_services_enabled' => true]);
    $admin = User::factory()->asAdmin()->create();
    foreach (ProductType::cases() as $type) {
        $admin->givePermissionTo([
            Permission::findOrCreate("view_any_{$type->value}::service", 'web'),
            Permission::findOrCreate("publish_{$type->value}_service", 'web'),
        ]);
    }
    $admin->givePermissionTo([
        Permission::findOrCreate('reject_service', 'web'),
        Permission::findOrCreate('archive_service', 'web'),
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($admin);

    $vendor = VendorProfile::factory()->approved()->create();
    $category = Category::factory()->create(['allowed_product_types' => ['rental', 'sale', 'digital']]);
    foreach (ProductType::cases() as $type) {
        $factory = match ($type) {
            ProductType::Rental => Service::factory()->rental(),
            ProductType::Sale => Service::factory()->sale(),
            ProductType::Digital => Service::factory()->digital(),
        };
        $make = fn (string $name, string $state) => $factory->{$state}()->create([
            'vendor_profile_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => ['en' => $name, 'ar' => $name],
        ]);
        $approved = collect([$make("{$type->value} approve 1", 'pendingReview'), $make("{$type->value} approve 2", 'pendingReview')]);
        $rejected = collect([$make("{$type->value} reject 1", 'pendingReview'), $make("{$type->value} reject 2", 'pendingReview')]);
        $archived = collect([$make("{$type->value} archive 1", 'published'), $make("{$type->value} archive 2", 'published')]);
        $unrelated = $make("{$type->value} unrelated published", 'published');
        foreach ($approved as $service) {
            $service->addMedia(UploadedFile::fake()->image('bulk-approval.jpg'))->toMediaCollection('gallery');
        }

        expect(BulkApproveServicesAction::execute($approved, $admin))->toBe(2)
            ->and(BulkRejectServicesAction::execute($rejected, ['en' => 'Bulk rejection.', 'ar' => 'رفض جماعي.'], $admin))->toBe(2)
            ->and(BulkArchiveServicesAction::execute($archived, $admin))->toBe(2);

        expect($approved->every(fn (Service $service): bool => $service->fresh()->status instanceof PublishedState))->toBeTrue()
            ->and($rejected->every(fn (Service $service): bool => $service->fresh()->status instanceof RejectedState))->toBeTrue()
            ->and($archived->every(fn (Service $service): bool => $service->fresh()->status instanceof ArchivedState))->toBeTrue()
            ->and($unrelated->fresh()->status)->toBeInstanceOf(PublishedState::class);
    }
});

it('paginates the admin service table while retaining type isolation on later pages', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('view_any_rental::service', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($admin);

    $vendor = VendorProfile::factory()->approved()->create();
    $category = Category::factory()->create(['allowed_product_types' => ['rental', 'sale']]);
    $rentals = Service::factory()->count(27)->rental()->create([
        'vendor_profile_id' => $vendor->id,
        'category_id' => $category->id,
    ]);
    $otherType = Service::factory()->sale()->create([
        'vendor_profile_id' => $vendor->id,
        'category_id' => $category->id,
    ]);

    Livewire::test(ListRentalServices::class)
        ->set('tableRecordsPerPage', 25)
        ->set('paginators.page', 2)
        ->assertCanSeeTableRecords([$rentals->last()])
        ->assertCanNotSeeTableRecords([$otherType]);

    expect($rentals->last()->fresh()->status)->toBeInstanceOf(DraftState::class)
        ->and($otherType->fresh()->status)->toBeInstanceOf(DraftState::class);
});

it('archives through the admin action for a permissioned super-admin without the admin role', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $admin->syncRoles('super_admin');
    $admin->givePermissionTo(Permission::findOrCreate('archive_service', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($admin);
    $service = Service::factory()->sale()->published()->create();

    expect($admin->hasRole('admin'))->toBeFalse()
        ->and($admin->hasRole('super_admin'))->toBeTrue()
        ->and(app(ArchiveServiceAction::class)->execute($service, $admin)->fresh()->status)->toBeInstanceOf(ArchivedState::class)
        ->and(Context::get('trigger_kind'))->toBe(TriggerKind::Admin->value);
});

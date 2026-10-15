<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Filament\Vendor\Pages\VendorServicesListPage;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorDigitalServiceResource;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Services\VendorOnboardingChecklistService;
use App\Modules\Identity\Domain\Enums\ChecklistItemStatus;
use App\Modules\Identity\Domain\Enums\VendorOnboardingChecklistItemKey;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorApprovedProductTypeResource\Pages\ListVendorApprovedProductTypes;
use App\Modules\Identity\Filament\Resources\VendorProfileResource;
use App\Modules\Identity\Filament\Vendor\Pages\VendorApprovalStatusPage;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    config(['catalog.digital_services_enabled' => true]);
});

function productTypeApprovalChecklistVendor(): VendorProfile
{
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    $vendor = VendorProfile::factory()->approved()->create([
        'business_name' => ['en' => 'Approval Guidance Vendor', 'ar' => 'مقدم خدمات حالة الاعتماد'],
        'address_line' => ['en' => '1 Test Street', 'ar' => '١ شارع الاختبار'],
        'business_type' => 'individual',
        'national_id' => '29901011234567',
        'primary_governorate_id' => $governorate->id,
        'primary_city_id' => $city->id,
        'bank_name' => 'Test Bank',
        'bank_account_holder' => 'Approval Guidance Vendor',
        'bank_iban' => 'EG380019000500000000263180002',
    ]);

    VendorBusinessHour::factory()->create(['vendor_profile_id' => $vendor->id]);
    VendorCoverageArea::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'city_id' => $vendor->primary_city_id,
    ]);

    foreach (['national_id', 'iban_proof'] as $type) {
        VendorDocument::factory()->approved()->create([
            'vendor_profile_id' => $vendor->id,
            'doc_type' => $type,
            'expires_at' => today(),
        ]);
    }

    return $vendor;
}

it('makes the existing product-type approval workflow discoverable from the read-only list', function (string $locale): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('approve_vendor_for_type', 'web'));
    $admin->givePermissionTo(Permission::findOrCreate('view_any_vendor::approved::product::type', 'web'));
    $admin->givePermissionTo(Permission::findOrCreate('view_any_vendor::profile', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($admin);

    Livewire::test(ListVendorApprovedProductTypes::class)
        ->assertSee(__('identity::identity.product_type_approval_guidance'))
        ->assertSee(__('identity.actions.manage_product_type_approvals'))
        ->assertSee(VendorProfileResource::getUrl('index', panel: 'admin'));
})->with(['en', 'ar']);

it('explains pending type access on the vendor dashboard and keeps all create routes blocked', function (string $locale): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $vendor = productTypeApprovalChecklistVendor();
    $this->actingAs($vendor->user, 'vendor');

    $checklist = app(VendorOnboardingChecklistService::class)->forVendor($vendor);
    $approvedTypes = collect($checklist->items)->firstWhere('key', VendorOnboardingChecklistItemKey::ApprovedTypes);
    expect($checklist->nextRecommendedAction->key)->toBe(VendorOnboardingChecklistItemKey::ApprovedTypes)
        ->and($checklist->nextRecommendedAction->url)->toBe(VendorApprovalStatusPage::getUrl(panel: 'vendor'))
        ->and($approvedTypes->url)->toBe(VendorApprovalStatusPage::getUrl(panel: 'vendor'));
    foreach (ProductType::cases() as $type) {
        expect($approvedTypes->subText)->toContain($type->label());
    }

    Livewire::test(VendorServicesListPage::class)
        ->assertActionDisabled('addRental')
        ->assertActionDisabled('addSale')
        ->assertActionDisabled('addDigital')
        ->assertSee(__('vendor-portal.services.pending_product_types', [
            'types' => collect(ProductType::cases())
                ->map(fn (ProductType $type): string => $type->label())
                ->implode(__('vendor-portal.services.product_type_separator')),
        ]))
        ->assertActionVisible('checkProductTypeApprovalStatus');

    $this->get(VendorApprovalStatusPage::getUrl(panel: 'vendor'))
        ->assertOk()
        ->assertSee(__('vendor-portal.approval.per_type_guidance'));

    foreach ([VendorRentalServiceResource::class, VendorSaleServiceResource::class, VendorDigitalServiceResource::class] as $resource) {
        expect($resource::canAccess())->toBeFalse()->and($resource::canCreate())->toBeFalse();
        $this->get($resource::getUrl('create', panel: 'vendor'))->assertForbidden();
    }
})->with(['en', 'ar']);

it('allows only the approved product type while keeping other types unavailable', function (string $locale): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $vendor = productTypeApprovalChecklistVendor();
    VendorApprovedProductType::factory()->forType(ProductType::Sale)->create(['vendor_profile_id' => $vendor->id]);
    $this->actingAs($vendor->user, 'vendor');

    $checklist = app(VendorOnboardingChecklistService::class)->forVendor($vendor);
    expect($checklist->nextRecommendedAction->key)->toBe(VendorOnboardingChecklistItemKey::ServiceDrafted)
        ->and($checklist->nextRecommendedAction->url)->toBe(VendorSaleServiceResource::getUrl('create', panel: 'vendor'));
    $approvedTypes = collect($checklist->items)->firstWhere('key', VendorOnboardingChecklistItemKey::ApprovedTypes);
    foreach (ProductType::cases() as $type) {
        $status = $type === ProductType::Sale ? 'approved' : 'pending';
        expect($approvedTypes->subText)->toContain(__('identity::vendor-onboarding.approved_types.status_line', [
            'type' => $type->label(),
            'status' => __('identity::vendor-onboarding.approved_types.'.$status),
        ]));
    }

    Livewire::test(VendorServicesListPage::class)
        ->assertActionEnabled('addSale')
        ->assertActionDisabled('addRental')
        ->assertActionDisabled('addDigital');

    $this->get(VendorSaleServiceResource::getUrl('create', panel: 'vendor'))->assertOk();
    $this->get(VendorRentalServiceResource::getUrl('create', panel: 'vendor'))->assertForbidden();
    $this->get(VendorDigitalServiceResource::getUrl('create', panel: 'vendor'))->assertForbidden();

    $statusPage = $this->get(VendorApprovalStatusPage::getUrl(panel: 'vendor'))->assertOk();
    foreach (ProductType::cases() as $type) {
        $statusPage->assertSee($type->label());
    }
    $statusPage->assertSee(__('vendor-portal.approval.approved'))
        ->assertSee(__('vendor-portal.approval.pending'));
})->with(['en', 'ar']);

it('excludes disabled Digital from pending guidance when Rental and Sale are approved', function (string $locale): void {
    app()->setLocale($locale);
    config(['catalog.digital_services_enabled' => false]);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $vendor = productTypeApprovalChecklistVendor();
    foreach ([ProductType::Rental, ProductType::Sale] as $type) {
        VendorApprovedProductType::factory()->forType($type)->create(['vendor_profile_id' => $vendor->id]);
    }
    $this->actingAs($vendor->user, 'vendor');

    $checklist = app(VendorOnboardingChecklistService::class)->forVendor($vendor);
    $approvedTypes = collect($checklist->items)->firstWhere('key', VendorOnboardingChecklistItemKey::ApprovedTypes);
    expect($approvedTypes->status)->toBe(ChecklistItemStatus::Complete)
        ->and($approvedTypes->url)->toBeNull()
        ->and($approvedTypes->subText)->toContain(ProductType::Rental->label())
        ->and($approvedTypes->subText)->toContain(ProductType::Sale->label())
        ->and($approvedTypes->subText)->not->toContain(__('identity::vendor-onboarding.approved_types.status_line', [
            'type' => ProductType::Digital->label(),
            'status' => __('identity::vendor-onboarding.approved_types.pending'),
        ]))
        ->and($approvedTypes->subText)->not->toContain(__('identity::vendor-onboarding.approved_types.status_line', [
            'type' => ProductType::Digital->label(),
            'status' => __('identity::vendor-onboarding.approved_types.approved'),
        ]))
        ->and($approvedTypes->subText)->toContain(__('identity::vendor-onboarding.approved_types.digital_unavailable'));

    Livewire::test(VendorServicesListPage::class)
        ->assertActionEnabled('addRental')
        ->assertActionEnabled('addSale')
        ->assertDontSee(__('vendor-portal.services.create_digital'))
        ->assertSee(__('vendor-portal.services.digital_services_disabled'));

    $this->get(VendorDigitalServiceResource::getUrl('create', panel: 'vendor'))->assertForbidden();
    $this->get(VendorApprovalStatusPage::getUrl(panel: 'vendor'))
        ->assertOk()
        ->assertSee(__('vendor-portal.approval.unavailable'))
        ->assertSee(__('vendor-portal.approval.per_type_guidance'));
})->with(['en', 'ar']);

it('keeps a disabled Digital-only legacy approval unavailable and points vendors to active-type status', function (string $locale): void {
    app()->setLocale($locale);
    config(['catalog.digital_services_enabled' => false]);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $vendor = productTypeApprovalChecklistVendor();
    VendorApprovedProductType::factory()->forType(ProductType::Digital)->create(['vendor_profile_id' => $vendor->id]);
    $this->actingAs($vendor->user, 'vendor');

    $checklist = app(VendorOnboardingChecklistService::class)->forVendor($vendor);
    $approvedTypes = collect($checklist->items)->firstWhere('key', VendorOnboardingChecklistItemKey::ApprovedTypes);
    expect($approvedTypes->status)->toBe(ChecklistItemStatus::Pending)
        ->and($approvedTypes->url)->toBe(VendorApprovalStatusPage::getUrl(panel: 'vendor'))
        ->and($approvedTypes->subText)->toContain(ProductType::Rental->label())
        ->and($approvedTypes->subText)->toContain(ProductType::Sale->label())
        ->and($approvedTypes->subText)->not->toContain(__('identity::vendor-onboarding.approved_types.status_line', [
            'type' => ProductType::Digital->label(),
            'status' => __('identity::vendor-onboarding.approved_types.pending'),
        ]))
        ->and($approvedTypes->subText)->not->toContain(__('identity::vendor-onboarding.approved_types.status_line', [
            'type' => ProductType::Digital->label(),
            'status' => __('identity::vendor-onboarding.approved_types.approved'),
        ]))
        ->and($approvedTypes->subText)->toContain(__('identity::vendor-onboarding.approved_types.digital_unavailable'))
        ->and($checklist->nextRecommendedAction->key)->toBe(VendorOnboardingChecklistItemKey::ApprovedTypes)
        ->and($checklist->nextRecommendedAction->url)->toBe(VendorApprovalStatusPage::getUrl(panel: 'vendor'));

    Livewire::test(VendorServicesListPage::class)
        ->assertActionDisabled('addRental')
        ->assertActionDisabled('addSale')
        ->assertDontSee(__('vendor-portal.services.create_digital'))
        ->assertSee(__('vendor-portal.services.digital_services_disabled'))
        ->assertSee(__('vendor-portal.services.pending_product_types', [
            'types' => implode(__('vendor-portal.services.product_type_separator'), [
                ProductType::Rental->label(),
                ProductType::Sale->label(),
            ]),
        ]))
        ->assertActionVisible('checkProductTypeApprovalStatus');

    $this->get(VendorDigitalServiceResource::getUrl('create', panel: 'vendor'))->assertForbidden();
    $this->get(VendorApprovalStatusPage::getUrl(panel: 'vendor'))
        ->assertOk()
        ->assertSee(__('vendor-portal.approval.unavailable'));
})->with(['en', 'ar']);

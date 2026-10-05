<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Filament\Vendor\Pages\VendorCategoriesPage;
use App\Modules\Catalog\Filament\Vendor\Pages\VendorServicesListPage;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorDigitalServiceResource;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource;
use App\Modules\Identity\Application\Services\VendorOnboardingChecklistService;
use App\Modules\Identity\Domain\Enums\VendorOnboardingChecklistItemKey;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\VendorBusinessHoursPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorCoverageAreasPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorDocumentsPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorPhoneVerificationPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorProfilePage;
use App\Modules\Identity\Filament\Vendor\Widgets\VendorOnboardingChecklistWidget;
use Filament\Facades\Filament;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
});

it('blocks unapproved operations even with stale type grants while preserving onboarding', function (string $locale, string $status): void {
    app()->setLocale($locale);
    $vendor = VendorProfile::factory()->create(['approval_status' => $status]);
    foreach (ProductType::cases() as $type) {
        VendorApprovedProductType::factory()->forType($type)->create(['vendor_profile_id' => $vendor->id]);
    }
    $this->actingAs($vendor->user, 'vendor');

    foreach ([VendorServicesListPage::class, VendorCategoriesPage::class] as $page) {
        expect($page::canAccess())->toBeFalse();
        $this->get($page::getUrl(panel: 'vendor'))->assertForbidden();
    }
    foreach ([VendorRentalServiceResource::class, VendorSaleServiceResource::class, VendorDigitalServiceResource::class] as $resource) {
        expect($resource::canAccess())->toBeFalse()->and($resource::canCreate())->toBeFalse();
        $this->get($resource::getUrl(panel: 'vendor'))->assertForbidden();
        $this->get($resource::getUrl('create', panel: 'vendor'))->assertForbidden();
    }
    foreach ([VendorProfilePage::class, VendorDocumentsPage::class, VendorBusinessHoursPage::class, VendorCoverageAreasPage::class] as $page) {
        $this->get($page::getUrl(panel: 'vendor'))->assertOk();
    }

    $checklist = app(VendorOnboardingChecklistService::class)->forVendor($vendor);
    foreach ($checklist->items as $item) {
        if (in_array($item->key, [VendorOnboardingChecklistItemKey::ServiceDrafted, VendorOnboardingChecklistItemKey::ServiceSubmitted], true)) {
            expect($item->url)->toBeNull();
        }
    }

    Sanctum::actingAs($vendor->user, ['*'], 'web');
    $this->getJson('/api/v1/vendor/services')->assertForbidden()->assertJsonPath('errors.0.code', 'vendor_approval_required');
})->with(['en', 'ar'])->with(['pending', 'rejected', 'changes_requested']);

it('keeps approved catalog access available', function (): void {
    $approved = VendorProfile::factory()->approved()->create();
    $this->actingAs($approved->user, 'vendor');

    expect(VendorServicesListPage::canAccess())->toBeTrue()
        ->and(VendorCategoriesPage::canAccess())->toBeTrue();

    $this->get(VendorServicesListPage::getUrl(panel: 'vendor'))->assertOk();
    $this->get(VendorCategoriesPage::getUrl(panel: 'vendor'))->assertOk();
});

it('requires phone verification after approval before operational access', function (): void {
    $approved = VendorProfile::factory()->approved()->create();
    $approved->user->forceFill(['phone_verified_at' => null])->save();
    $this->actingAs($approved->user->fresh(), 'vendor');

    expect(VendorServicesListPage::canAccess())->toBeFalse();

    $this->get(VendorServicesListPage::getUrl(panel: 'vendor'))->assertForbidden();
    $this->get(VendorPhoneVerificationPage::getUrl(panel: 'vendor'))->assertOk();
    Sanctum::actingAs($approved->user, ['*'], 'web');
    $this->getJson('/api/v1/vendor/services')
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'phone_verification_required');
});

it('keeps a resumable checklist visible for an approved vendor with unfinished setup', function (): void {
    $approved = VendorProfile::factory()->approved()->create();
    $this->actingAs($approved->user, 'vendor');

    expect(VendorOnboardingChecklistWidget::canView())->toBeTrue();
});

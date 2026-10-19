<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Actions\UpdateVendorProfileAction;
use App\Modules\Identity\Application\Services\VendorOnboardingChecklistService;
use App\Modules\Identity\Domain\Enums\ChecklistItemStatus;
use App\Modules\Identity\Domain\Enums\DayOfWeek;
use App\Modules\Identity\Domain\Enums\VendorOnboardingChecklistItemKey;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource\Pages\ViewVendorDocument;
use App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource;
use App\Modules\Identity\Filament\Vendor\Pages\RegisterVendorPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorBusinessHoursPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorPhoneVerificationPage;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('collects a localized city during vendor registration and resets it after a governorate change', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id, 'name' => ['en' => 'Registration City', 'ar' => 'مدينة التسجيل']]);
    $otherGovernorate = Governorate::factory()->create();
    $page = Livewire::test(RegisterVendorPage::class)
        ->fillForm(['primary_governorate_id' => $governorate->id, 'primary_city_id' => $city->id]);
    $page->assertFormFieldExists('primary_city_id');
    $page->set('data.primary_governorate_id', $otherGovernorate->id)->assertSet('data.primary_city_id', null);
});

it('does not persist visual bank placeholders through the shared profile action', function (): void {
    $vendor = VendorProfile::factory()->create();
    app(UpdateVendorProfileAction::class)->execute($vendor, [
        'bank_name' => ' — ', 'bank_account_holder' => '—', 'bank_iban' => '-', 'bank_swift_bic' => '–',
    ]);
    foreach (['bank_name', 'bank_account_holder', 'bank_iban', 'bank_swift_bic'] as $field) {
        expect($vendor->fresh()->getRawOriginal($field))->toBeNull();
    }
});

it('keeps document edit hidden when an admin can review but lacks update permission', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->create();
    foreach (['view_any_vendor::document::filament', 'view_vendor::document::filament', 'review_vendor_documents'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($admin);
    $document = VendorDocument::factory()->create();
    Livewire::test(ViewVendorDocument::class, ['record' => $document->getRouteKey()])
        ->assertSee($document->public_id);
    expect(VendorDocumentFilamentResource::canEdit($document))->toBeFalse();
    $this->get(VendorDocumentFilamentResource::getUrl('edit', ['record' => $document]))->assertForbidden();
});

it('uses the newest required documents with stable status precedence in onboarding', function (string $latestStatus, ChecklistItemStatus $expected): void {
    $vendor = VendorProfile::factory()->create(['business_type' => 'individual']);
    foreach (['national_id', 'iban_proof'] as $type) {
        VendorDocument::factory()->approved()->create(['vendor_profile_id' => $vendor->id, 'doc_type' => $type, 'created_at' => now()]);
    }
    VendorDocument::factory()->create(['vendor_profile_id' => $vendor->id, 'doc_type' => 'national_id', 'status' => $latestStatus, 'created_at' => now()]);
    $checklist = app(VendorOnboardingChecklistService::class)->forVendor($vendor);
    $items = collect($checklist->items)->keyBy(fn ($item) => $item->key->value);
    expect($items[VendorOnboardingChecklistItemKey::DocsUploaded->value]->status)->toBe(ChecklistItemStatus::Complete)
        ->and($items[VendorOnboardingChecklistItemKey::DocsApproved->value]->status)->toBe($expected);
})->with([
    ['approved', ChecklistItemStatus::Complete],
    ['pending', ChecklistItemStatus::Info],
    ['rejected', ChecklistItemStatus::Danger],
]);

it('shows send and confirm controls for an unverified vendor phone', function (string $locale): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $vendor = VendorProfile::factory()->approved()->create();
    $vendor->user->update(['phone_verified_at' => null]);
    $this->actingAs($vendor->user->fresh(), 'vendor');
    Livewire::test(VendorPhoneVerificationPage::class)
        ->assertSee(__('vendor-portal.phone_verification.send_code'))
        ->assertSee(__('vendor-portal.phone_verification.verify'));
})->with(['en', 'ar']);

it('shows and requires time fields when Friday is open', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $vendor = VendorProfile::factory()->approved()->create();
    $this->actingAs($vendor->user, 'vendor');
    $prefix = 'day_'.DayOfWeek::Friday->value;
    Livewire::test(VendorBusinessHoursPage::class)
        ->fillForm([$prefix.'_open' => true, $prefix.'_opens_at' => null, $prefix.'_closes_at' => null])
        ->call('save')->assertHasFormErrors([$prefix.'_opens_at' => 'required', $prefix.'_closes_at' => 'required']);
    Livewire::test(VendorBusinessHoursPage::class)
        ->fillForm([$prefix.'_open' => true, $prefix.'_opens_at' => '10:00', $prefix.'_closes_at' => '18:00'])
        ->call('save')->assertHasNoFormErrors();
    expect($vendor->businessHours()->where('day_of_week', DayOfWeek::Friday)->firstOrFail()->opens_at)->toStartWith('10:00');
});

it('excludes expired and superseded documents from the upcoming expiry filter', function (): void {
    $vendor = VendorProfile::factory()->create();
    VendorDocument::factory()->approved()->create(['vendor_profile_id' => $vendor->id, 'doc_type' => 'national_id', 'expires_at' => today()->addDays(5)]);
    VendorDocument::factory()->approved()->create(['vendor_profile_id' => $vendor->id, 'doc_type' => 'national_id', 'expires_at' => today()->addDays(90)]);
    $upcoming = VendorDocument::factory()->approved()->create(['vendor_profile_id' => $vendor->id, 'doc_type' => 'iban_proof', 'expires_at' => today()->addDays(30)]);
    VendorDocument::factory()->approved()->create(['vendor_profile_id' => $vendor->id, 'doc_type' => 'other', 'expires_at' => today()->subDay()]);
    expect(VendorDocument::query()->latestPerType()->where('status', 'approved')->expiringWithinDays(30)->pluck('id')->all())->toBe([$upcoming->id]);
});

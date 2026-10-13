<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Actions\AutoSuspendForExpiredDocAction;
use App\Modules\Identity\Application\Actions\UpdateVendorProfileAction;
use App\Modules\Identity\Application\Services\VendorOnboardingChecklistService;
use App\Modules\Identity\Domain\Enums\ChecklistItemStatus;
use App\Modules\Identity\Domain\Enums\DayOfWeek;
use App\Modules\Identity\Domain\Enums\VendorOnboardingChecklistItemKey;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource;
use App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource\Pages\ViewVendorDocument;
use App\Modules\Identity\Filament\Vendor\Pages\RegisterVendorPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorBusinessHoursPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorDocumentsPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorPhoneVerificationPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorProfilePage;
use Carbon\Carbon;
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

it('shows legacy bank placeholders as empty and points the banking checklist to the actual bank form', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $vendor = VendorProfile::factory()->approved()->create(['bank_name' => '—', 'bank_account_holder' => '-', 'bank_iban' => '—', 'bank_swift_bic' => '–']);
    $this->actingAs($vendor->user, 'vendor');
    Livewire::test(VendorProfilePage::class)->assertFormSet([
        'bank_name' => null, 'bank_account_holder' => null, 'bank_iban' => null, 'bank_swift' => null,
    ]);
    $banking = collect(app(VendorOnboardingChecklistService::class)->forVendor($vendor)->items)
        ->first(fn ($item): bool => $item->key === VendorOnboardingChecklistItemKey::Banking);
    expect($banking->url)->toBe(VendorProfilePage::getUrl(panel: 'vendor'));
});

it('requires both review and update permissions for a panel authorized admins document edit route', function (bool $canReview, bool $canUpdate): void {
    config(['app.admin_mfa_required' => false]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->asAdmin()->create();
    $permissions = ['view_any_vendor::document::filament', 'view_vendor::document::filament'];
    if ($canReview) {
        $permissions[] = 'review_vendor_documents';
    }
    if ($canUpdate) {
        $permissions[] = 'update_vendor::document::filament';
    }
    foreach ($permissions as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($admin);
    $document = VendorDocument::factory()->create();
    $this->get(VendorDocumentFilamentResource::getUrl('index'))->assertOk();
    $this->get(VendorDocumentFilamentResource::getUrl('view', ['record' => $document]))->assertOk();
    Livewire::test(ViewVendorDocument::class, ['record' => $document->getRouteKey()])->assertActionHidden('edit');
    $this->get(VendorDocumentFilamentResource::getUrl('edit', ['record' => $document]))->assertForbidden();
})->with([[true, false], [false, true]]);

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

it('keeps document status precedence stable across rejected expired missing and pending requirements', function (array $documents, ChecklistItemStatus $expected): void {
    config(['app.timezone' => 'UTC']);
    $this->travelTo(Carbon::parse('2026-10-13 23:59:59', 'UTC'));
    $vendor = VendorProfile::factory()->create(['business_type' => 'company']);
    foreach ($documents as $type => $attributes) {
        VendorDocument::factory()->create(['vendor_profile_id' => $vendor->id, 'doc_type' => $type, ...$attributes]);
    }
    $status = collect(app(VendorOnboardingChecklistService::class)->forVendor($vendor)->items)
        ->first(fn ($item): bool => $item->key === VendorOnboardingChecklistItemKey::DocsApproved)->status;
    expect($status)->toBe($expected);
})->with([
    'expired beats missing and pending' => [[
        'cr' => ['status' => 'approved', 'expires_at' => '2026-10-12'],
        'iban_proof' => ['status' => 'pending'],
    ], ChecklistItemStatus::Warning],
    'rejected beats expired and missing' => [[
        'cr' => ['status' => 'approved', 'expires_at' => '2026-10-12'],
        'iban_proof' => ['status' => 'rejected'],
    ], ChecklistItemStatus::Danger],
    'missing beats pending' => [[
        'cr' => ['status' => 'pending'], 'iban_proof' => ['status' => 'approved'],
    ], ChecklistItemStatus::Pending],
    'valid through expiry day' => [[
        'cr' => ['status' => 'approved', 'expires_at' => '2026-10-13'],
        'tax_card' => ['status' => 'approved'], 'iban_proof' => ['status' => 'approved'],
    ], ChecklistItemStatus::Complete],
]);

it('explains required missing documents even when other uploaded documents are approved', function (string $locale): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $vendor = VendorProfile::factory()->create(['business_type' => 'company']);
    $this->actingAs($vendor->user, 'vendor');
    VendorDocument::factory()->approved()->create(['vendor_profile_id' => $vendor->id, 'doc_type' => 'cr']);
    VendorDocument::factory()->approved()->create(['vendor_profile_id' => $vendor->id, 'doc_type' => 'iban_proof']);
    Livewire::test(VendorDocumentsPage::class)->assertSee(__('identity.document_type.tax_card'))->assertSee(__('identity.compliance.required_missing'));
    $items = collect(app(VendorOnboardingChecklistService::class)->forVendor($vendor)->items)->keyBy(fn ($item) => $item->key->value);
    expect($items[VendorOnboardingChecklistItemKey::DocsUploaded->value]->status)->toBe(ChecklistItemStatus::Pending)
        ->and($items[VendorOnboardingChecklistItemKey::DocsApproved->value]->status)->toBe(ChecklistItemStatus::Pending);
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

it('keeps the latest creation timestamp for backfilled document history and breaks timestamp ties by ID', function (): void {
    $vendor = VendorProfile::factory()->create();
    $current = VendorDocument::factory()->approved()->create(['vendor_profile_id' => $vendor->id, 'doc_type' => 'national_id', 'expires_at' => today()->addDays(20), 'created_at' => now()]);
    VendorDocument::factory()->approved()->create(['vendor_profile_id' => $vendor->id, 'doc_type' => 'national_id', 'expires_at' => today()->addDays(90), 'created_at' => now()->subMonth()]);
    VendorDocument::factory()->approved()->create(['doc_type' => 'national_id', 'created_at' => now()->addDay()]);
    expect($vendor->documents()->latestPerType()->where('doc_type', 'national_id')->sole()->id)->toBe($current->id)
        ->and($vendor->documents()->latestPerType()->where('status', 'approved')->expiringWithinDays(30)->pluck('id')->all())->toBe([$current->id]);
    $tie = VendorDocument::factory()->approved()->create(['vendor_profile_id' => $vendor->id, 'doc_type' => 'national_id', 'created_at' => $current->created_at, 'expires_at' => null]);
    expect($vendor->documents()->latestPerType()->where('doc_type', 'national_id')->sole()->id)->toBe($tie->id)
        ->and($vendor->documents()->latestPerType()->expiringWithinDays(30)->count())->toBe(0);
});

it('suspends for the actual expired current critical document despite higher ID backfilled history', function (): void {
    $this->travelTo(Carbon::parse('2026-10-13 12:00:00', 'UTC'));
    $vendor = VendorProfile::factory()->approved()->create(['business_type' => 'individual']);
    $expired = VendorDocument::factory()->approved()->create(['vendor_profile_id' => $vendor->id, 'doc_type' => 'national_id', 'expires_at' => today()->subDay(), 'created_at' => now()->subDay(), 'is_critical' => true]);
    $backfilled = VendorDocument::factory()->approved()->create(['vendor_profile_id' => $vendor->id, 'doc_type' => 'national_id', 'expires_at' => today()->subMonth(), 'created_at' => now()->subMonth(), 'is_critical' => true]);
    app(AutoSuspendForExpiredDocAction::class)->execute($backfilled);
    expect($vendor->fresh()->approval_status->getMorphClass())->toBe('approved');
    app(AutoSuspendForExpiredDocAction::class)->execute($expired);
    expect($vendor->fresh()->approval_status->getMorphClass())->toBe('suspended');
});

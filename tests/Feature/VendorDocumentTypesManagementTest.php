<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Filament\Vendor\Pages\VendorServicesListPage;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Actions\ApproveVendorForTypeAction;
use App\Modules\Identity\Application\Actions\AutoSuspendForExpiredDocAction;
use App\Modules\Identity\Application\Actions\DeleteVendorDocumentTypeAction;
use App\Modules\Identity\Application\Actions\ReuploadVendorDocumentAction;
use App\Modules\Identity\Application\Actions\SaveVendorDocumentTypeAction;
use App\Modules\Identity\Application\Services\RequiredVendorDocumentTypesResolver;
use App\Modules\Identity\Application\Services\VendorApprovalEligibilityService;
use App\Modules\Identity\Application\Services\VendorOnboardingChecklistService;
use App\Modules\Identity\Domain\Enums\ChecklistItemStatus;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Enums\VendorOnboardingChecklistItemKey;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorDocumentType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource\Pages\ReviewVendorApplication;
use App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource\Pages\ListVendorDocuments;
use App\Modules\Identity\Filament\Resources\VendorDocumentTypeResource\Pages\ManageVendorDocumentTypes;
use App\Modules\Identity\Filament\Vendor\Pages\VendorDocumentsPage;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

function documentSettingsAdmin(): User
{
    $admin = User::factory()->superAdmin()->create();
    foreach (['review_vendor_documents', 'approve_vendor_profile', 'approve_vendor_for_type', 'view_any_vendor::profile', 'view_vendor::profile', 'view_any_vendor::document::filament'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $admin;
}

function documentSettingsVendor(): VendorProfile
{
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    $user = User::factory()->asVendor()->phoneVerified()->create(['email_verified_at' => now()]);
    $vendor = VendorProfile::factory()->pending()->create([
        'user_id' => $user->id,
        'business_type' => 'individual',
        'business_name' => ['en' => 'Document Settings Vendor', 'ar' => 'مورد إعدادات المستندات'],
        'address_line' => ['en' => 'Test address', 'ar' => 'عنوان الاختبار'],
        'national_id' => '29901011234567',
        'primary_governorate_id' => $governorate->id,
        'primary_city_id' => $city->id,
        'bank_name' => 'Test Bank',
        'bank_account_holder' => $user->name,
        'bank_iban' => 'EG380019000500000000263180002',
    ]);
    VendorBusinessHour::factory()->create(['vendor_profile_id' => $vendor->id]);
    VendorCoverageArea::factory()->create(['vendor_profile_id' => $vendor->id, 'city_id' => $city->id]);

    return $vendor;
}

it('opens the document type settings screen for an authorized admin', function (): void {
    $this->actingAs(documentSettingsAdmin(), 'web');
    $this->get('/admin/vendor-document-types')->assertOk();
});

it('lets admin create edit and remove document types without deleting uploaded files', function (): void {
    $admin = documentSettingsAdmin();
    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $data = ['name' => ['en' => 'Business licence', 'ar' => 'رخصة النشاط'], 'required_for' => ['individual']];
    Livewire::test(ManageVendorDocumentTypes::class)
        ->callAction('create', data: $data)
        ->assertHasNoActionErrors();
    $type = VendorDocumentType::query()->where('name->en', 'Business licence')->firstOrFail();
    expect(app(RequiredVendorDocumentTypesResolver::class)->forBusinessType('individual'))->toContain($type->key);
    $document = VendorDocument::factory()->create(['doc_type' => $type->key]);

    Livewire::test(ManageVendorDocumentTypes::class)
        ->callTableAction('edit', $type, data: ['name' => ['en' => 'Licence copy', 'ar' => 'صورة الرخصة'], 'required_for' => []])
        ->assertHasNoTableActionErrors();
    expect(app(RequiredVendorDocumentTypesResolver::class)->forBusinessType('individual'))->not->toContain($type->key);
    Livewire::test(ManageVendorDocumentTypes::class)->callTableAction('delete', $type)->assertHasNoTableActionErrors();
    expect($type->refresh()->is_active)->toBeFalse()
        ->and(VendorDocument::query()->whereKey($document->id)->exists())->toBeTrue()
        ->and(app(RequiredVendorDocumentTypesResolver::class)->options())->not->toHaveKey($type->key)
        ->and(app(RequiredVendorDocumentTypesResolver::class)->label($type->key, 'ar'))->toBe('صورة الرخصة');
});

it('requires both translated names and restricts settings mutation to reviewers', function (): void {
    $admin = documentSettingsAdmin();
    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ManageVendorDocumentTypes::class)
        ->callAction('create', data: ['name' => ['en' => 'Licence', 'ar' => ''], 'required_for' => []])
        ->assertHasActionErrors(['name.ar' => 'required']);

    $user = User::factory()->create();
    $this->actingAs($user, 'web');
    expect(ManageVendorDocumentTypes::canAccess())->toBeFalse();
    $this->get('/admin/vendor-document-types')->assertForbidden();
    expect(fn () => app(SaveVendorDocumentTypeAction::class)->execute(null, ['name' => ['en' => 'Licence', 'ar' => 'رخصة'], 'required_for' => []], $user))->toThrow(HttpException::class);
    expect(fn () => app(DeleteVendorDocumentTypeAction::class)->execute(VendorDocumentType::query()->firstOrFail(), $user))->toThrow(HttpException::class);
});

it('keeps every built in type available in the actual vendor upload form', function (string $key): void {
    Storage::fake('tmp-for-tests');
    Storage::fake('local');
    config(['filesystems.default' => 'local', 'filesystems.disks.s3-private.key' => null]);
    $vendor = documentSettingsVendor();
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($vendor->user, 'vendor');

    Livewire::test(VendorDocumentsPage::class)->callAction('upload', data: [
        'doc_type' => $key,
        'file' => UploadedFile::fake()->createWithContent('document.pdf', file_get_contents(__DIR__.'/../Fixtures/vendor-document.pdf')),
    ])->assertHasNoActionErrors();
    expect($vendor->documents()->where('doc_type', $key)->exists())->toBeTrue();
})->with(['cr', 'tax_card', 'national_id', 'iban_proof', 'other']);

it('uploads reviews and reuploads custom document types through the vendor and admin forms', function (): void {
    Storage::fake('tmp-for-tests');
    Storage::fake('local');
    config(['filesystems.default' => 'local', 'filesystems.disks.s3-private.key' => null]);
    $admin = documentSettingsAdmin();
    $type = app(SaveVendorDocumentTypeAction::class)->execute(null, ['name' => ['en' => 'Licence', 'ar' => 'رخصة'], 'required_for' => []], $admin);
    $vendor = documentSettingsVendor();
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($vendor->user, 'vendor');
    Livewire::test(VendorDocumentsPage::class)->callAction('upload', data: [
        'doc_type' => $type->key,
        'file' => UploadedFile::fake()->createWithContent('licence.pdf', file_get_contents(__DIR__.'/../Fixtures/vendor-document.pdf')),
    ])->assertHasNoActionErrors();
    $document = $vendor->documents()->firstOrFail();
    Livewire::test(VendorDocumentsPage::class)->assertSee('Licence');

    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ListVendorDocuments::class)
        ->callTableAction('reject', $document, data: ['review_notes_en' => 'Upload a clearer copy', 'review_notes_ar' => 'ارفع نسخة أوضح'])
        ->assertHasNoTableActionErrors();
    $replacement = app(ReuploadVendorDocumentAction::class)->execute($document->refresh(), UploadedFile::fake()->createWithContent('replacement.pdf', file_get_contents(__DIR__.'/../Fixtures/vendor-document.pdf')), $vendor->user);
    Livewire::test(ListVendorDocuments::class)->assertSee('Licence')
        ->callTableAction('approve', $replacement)->assertHasNoTableActionErrors();
    expect($replacement->refresh()->status)->toBe(DocumentStatus::Approved);

    Sanctum::actingAs($vendor->user, ['*'], 'web');
    $this->getJson('/api/v1/vendor/documents')->assertOk()->assertJsonPath('data.0.doc_type', $type->key);
    $this->postJson('/api/v1/vendor/documents', [
        'doc_type' => $type->key,
        'file' => UploadedFile::fake()->createWithContent('api-licence.pdf', file_get_contents(__DIR__.'/../Fixtures/vendor-document.pdf')),
    ])->assertCreated()->assertJsonPath('data.doc_type', $type->key);
    app(DeleteVendorDocumentTypeAction::class)->execute($type, $admin);
    $this->postJson('/api/v1/vendor/documents', ['doc_type' => $type->key, 'file' => UploadedFile::fake()->createWithContent('removed.pdf', file_get_contents(__DIR__.'/../Fixtures/vendor-document.pdf'))])->assertUnprocessable();
});

it('approves a vendor with one required document or no required documents and permits all product types', function (int $requiredCount, int $uploadedCount): void {
    SubscriptionPlan::factory()->free()->create();
    $admin = documentSettingsAdmin();
    foreach (VendorDocumentType::query()->get() as $type) {
        app(SaveVendorDocumentTypeAction::class)->execute($type, ['name' => $type->getTranslations('name'), 'required_for' => $requiredCount === 1 && $type->key === 'national_id' ? ['individual'] : []], $admin);
    }
    $vendor = documentSettingsVendor();
    if ($uploadedCount === 1) {
        VendorDocument::factory()->approved()->create(['vendor_profile_id' => $vendor->id, 'doc_type' => 'national_id']);
    }
    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ReviewVendorApplication::class, ['record' => $vendor->public_id])
        ->assertActionEnabled('approve')->callAction('approve')->assertHasNoActionErrors();
    expect($vendor->refresh()->approval_status)->toBeInstanceOf(ApprovedState::class);

    foreach (ProductType::cases() as $productType) {
        foreach (['create', 'update', 'delete', 'publish'] as $operation) {
            Permission::findOrCreate("service.{$operation}.{$productType->value}.own", 'web');
        }
        app(ApproveVendorForTypeAction::class)->execute($vendor, $productType, $admin);
    }
    $this->actingAs($vendor->user->fresh(), 'vendor');
    expect(VendorServicesListPage::canAccess())->toBeTrue()->and($vendor->approvedTypes()->count())->toBe(3);
    foreach (app(VendorOnboardingChecklistService::class)->forVendor($vendor)->items as $item) {
        if (in_array($item->key, [VendorOnboardingChecklistItemKey::DocsUploaded, VendorOnboardingChecklistItemKey::DocsApproved], true)) {
            expect($item->status)->toBe(ChecklistItemStatus::Complete);
        }
    }
})->with([[1, 1], [0, 0], [0, 1]]);

it('keeps approval blocked until the configured required document is approved', function (): void {
    $admin = documentSettingsAdmin();
    foreach (VendorDocumentType::query()->get() as $type) {
        app(SaveVendorDocumentTypeAction::class)->execute($type, ['name' => $type->getTranslations('name'), 'required_for' => $type->key === 'national_id' ? ['individual'] : []], $admin);
    }
    $vendor = documentSettingsVendor();
    VendorDocument::factory()->approved()->create(['vendor_profile_id' => $vendor->id, 'doc_type' => 'iban_proof']);
    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ReviewVendorApplication::class, ['record' => $vendor->public_id])->assertActionDisabled('approve');
});

it('does not suspend an approved vendor for a critical document made optional or removed', function (bool $remove): void {
    $admin = documentSettingsAdmin();
    $type = VendorDocumentType::query()->where('key', 'national_id')->firstOrFail();
    $vendor = documentSettingsVendor();
    $vendor->update(['approval_status' => ApprovedState::class]);
    $document = VendorDocument::factory()->approved()->create(['vendor_profile_id' => $vendor->id, 'doc_type' => 'national_id', 'is_critical' => true, 'expires_at' => today()->subDay()]);
    if ($remove) {
        app(DeleteVendorDocumentTypeAction::class)->execute($type, $admin);
    } else {
        app(SaveVendorDocumentTypeAction::class)->execute($type, ['name' => $type->getTranslations('name'), 'required_for' => []], $admin);
    }
    app(AutoSuspendForExpiredDocAction::class)->execute($document);
    expect($vendor->refresh()->approval_status)->toBeInstanceOf(ApprovedState::class);
})->with([false, true]);

it('gates approval on an admin added required type and its current review status', function (string $status, bool $expired, bool $eligible): void {
    $admin = documentSettingsAdmin();
    VendorDocumentType::query()->update(['required_for' => '[]']);
    $type = app(SaveVendorDocumentTypeAction::class)->execute(null, ['name' => ['en' => 'Licence', 'ar' => 'رخصة'], 'required_for' => ['individual']], $admin);
    $vendor = documentSettingsVendor();
    expect(app(VendorApprovalEligibilityService::class)->evaluate($vendor)->passes('documents'))->toBeFalse();
    VendorDocument::factory()->create([
        'vendor_profile_id' => $vendor->id, 'doc_type' => $type->key,
        'status' => $status, 'expires_at' => $expired ? today()->subDay() : null,
    ]);
    expect(app(VendorApprovalEligibilityService::class)->evaluate($vendor)->passes('documents'))->toBe($eligible);
})->with([['pending', false, false], ['rejected', false, false], ['approved', false, true], ['approved', true, false]]);

it('preserves uploaded legacy document data when widening document types and seeding their settings', function (): void {
    $document = VendorDocument::factory()->approved()->create(['doc_type' => 'national_id', 'file_name' => 'existing-id.pdf']);
    $before = $document->fresh()->getRawOriginal();
    $migration = require app_path('Modules/Identity/Database/Migrations/2026_10_14_000001_configure_vendor_document_types.php');
    $migration->down();
    Schema::table('vendor_documents', function (Blueprint $table): void {
        $table->enum('doc_type', ['cr', 'tax_card', 'national_id', 'iban_proof', 'other'])->change();
    });
    $migration->up();
    expect($document->fresh()->getRawOriginal())->toEqual($before)
        ->and(VendorDocumentType::query()->count())->toBe(5);
    $custom = VendorDocument::factory()->create(['doc_type' => 'custom_type']);
    expect($custom->fresh()->doc_type_key)->toBe('custom_type');
});

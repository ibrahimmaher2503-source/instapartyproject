<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Actions\ApproveVendorForTypeAction;
use App\Modules\Identity\Application\Actions\ApproveVendorProfileAction;
use App\Modules\Identity\Application\Actions\AutoSuspendForExpiredDocAction;
use App\Modules\Identity\Application\Actions\RecordExpiredVendorDocumentAction;
use App\Modules\Identity\Application\Actions\RevokeVendorTypeAction;
use App\Modules\Identity\Application\Actions\SuspendVendorAction;
use App\Modules\Identity\Application\Actions\UnsuspendVendorAction;
use App\Modules\Identity\Application\Services\VendorApprovalEligibilityService;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Domain\Models\VendorComplianceEvent;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\PendingState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\RejectedState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\SuspendedState;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource\Pages\ListVendorApprovalQueue;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource\Pages\ReviewVendorApplication;
use App\Modules\Identity\Filament\Resources\VendorProfileResource\Pages\ListVendorProfiles;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

function vendorLifecycleActor(array $permissions): User
{
    $actor = User::factory()->create();
    foreach ($permissions as $permission) {
        $permissionModel = Permission::findOrCreate($permission, 'web');
        $actor->givePermissionTo($permissionModel);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $actor->unsetRelation('permissions');

    return $actor;
}

function eligibleVendorProfile(): VendorProfile
{
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    $user = User::factory()->phoneVerified()->create([
        'name' => 'QA Eligible Vendor',
        'email_verified_at' => now(),
        'phone_verified_at' => now(),
    ]);
    $profile = VendorProfile::factory()->pending()->create([
        'user_id' => $user->id,
        'business_name' => ['en' => 'QA Eligible Events', 'ar' => 'QA Eligible Events AR'],
        'address_line' => ['en' => 'QA address', 'ar' => 'QA address AR'],
        'business_type' => 'individual',
        'national_id' => '29901011234567',
        'primary_governorate_id' => $governorate->id,
        'primary_city_id' => $city->id,
        'bank_name' => 'QA Bank',
        'bank_account_holder' => 'QA Eligible Vendor',
        'bank_iban' => 'EG380019000500000000263180002',
    ]);

    VendorBusinessHour::factory()->create(['vendor_profile_id' => $profile->id]);
    VendorCoverageArea::factory()->create(['vendor_profile_id' => $profile->id, 'city_id' => $city->id]);

    foreach ([DocumentType::NationalId, DocumentType::IbanProof] as $type) {
        VendorDocument::factory()->approved()->create([
            'vendor_profile_id' => $profile->id,
            'doc_type' => $type,
            'expires_at' => today(),
            'is_critical' => true,
        ]);
    }

    return $profile;
}

it('denies incomplete approval with a localized structured checklist', function (string $locale): void {
    app()->setLocale($locale);
    $actor = vendorLifecycleActor(['approve_vendor_profile']);
    $profile = VendorProfile::factory()->pending()->create();

    try {
        app(ApproveVendorProfileAction::class)->execute($profile, $actor);
        $this->fail('Incomplete approval was not denied.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('eligibility')
            ->and($exception->errors()['eligibility'])->not->toBeEmpty();
    }

    expect($profile->refresh()->approval_status)->not->toBeInstanceOf(ApprovedState::class);
})->with(['en', 'ar']);

it('names the required documents and their current status when blocking company approval', function (string $locale): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $actor = vendorLifecycleActor(['view_any_vendor::profile', 'view_vendor::profile', 'approve_vendor_profile']);
    $this->actingAs($actor);

    $profile = eligibleVendorProfile();
    $profile->update([
        'business_type' => 'company',
        'commercial_register_no' => 'CR-TEST-1',
        'tax_id' => 'TAX-TEST-1',
    ]);
    VendorDocument::factory()->approved()->create([
        'vendor_profile_id' => $profile->id,
        'doc_type' => DocumentType::Cr,
        'expires_at' => null,
    ]);
    $profile->documents()
        ->where('doc_type', DocumentType::IbanProof->value)
        ->latest('created_at')
        ->latest('id')
        ->firstOrFail()
        ->update(['expires_at' => CarbonImmutable::now(config('app.timezone', 'UTC'))->subDay()->toDateString()]);

    $eligibility = app(VendorApprovalEligibilityService::class)->evaluate($profile->fresh());
    $missingTaxCard = __('identity::identity.approval_eligibility.document_issue', [
        'document' => __('identity::identity.document_type.tax_card'),
        'status' => __('identity::identity.approval_eligibility.document_status.missing'),
    ]);
    $expiredIbanProof = __('identity::identity.approval_eligibility.document_issue', [
        'document' => __('identity::identity.document_type.iban_proof'),
        'status' => __('identity::identity.approval_eligibility.document_status.expired'),
    ]);
    expect($eligibility->passes('documents'))->toBeFalse()
        ->and($eligibility->errors['documents'])->toContain($missingTaxCard)
        ->and($eligibility->errors['documents'])->toContain($expiredIbanProof);
    $pendingTaxCard = __('identity::identity.approval_eligibility.document_issue', [
        'document' => __('identity::identity.document_type.tax_card'),
        'status' => __('identity::identity.approval_eligibility.document_status.pending'),
    ]);

    Livewire::test(ListVendorProfiles::class)
        ->assertSuccessful()
        ->assertTableActionEnabled('approve', $profile);

    $queue = Livewire::test(ListVendorApprovalQueue::class)
        ->assertSuccessful()
        ->assertTableActionEnabled('approve', $profile)
        ->assertSee($eligibility->errors['documents'])
        ->mountTableAction('approve', $profile);
    VendorDocument::factory()->create([
        'vendor_profile_id' => $profile->id,
        'doc_type' => DocumentType::TaxCard,
        'status' => 'pending',
    ]);

    $queue->callMountedTableAction()
        ->assertNotified(Notification::make()
            ->title(__('identity.notifications.vendor_approval_failed'))
            ->body(implode(' ', [$pendingTaxCard, $expiredIbanProof]))
            ->danger());

    expect($profile->fresh()->approval_status)->toBeInstanceOf(PendingState::class);
})->with(['en', 'ar']);

it('accepts required documents through the end of the configured timezone day', function (): void {
    $profile = eligibleVendorProfile();
    $timezone = (string) config('app.timezone', 'UTC');
    $endOfDay = CarbonImmutable::now($timezone)->endOfDay();

    expect(app(VendorApprovalEligibilityService::class)->evaluate($profile, $endOfDay)->eligible)->toBeTrue()
        ->and(app(VendorApprovalEligibilityService::class)->evaluate($profile, $endOfDay->addSecond())->eligible)->toBeFalse();
});

it('explains incomplete approval in the queue and review while allowing valid rejection', function (string $locale): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(vendorLifecycleActor(['view_any_vendor::profile', 'view_vendor::profile', 'approve_vendor_profile', 'reject_vendor_profile']));
    $profile = eligibleVendorProfile();
    $profile->businessHours()->delete();
    $failure = Notification::make()
        ->title(__('identity.notifications.vendor_approval_failed'))
        ->body(__('identity::identity.approval_eligibility.hours'))
        ->danger();

    Livewire::test(ListVendorApprovalQueue::class)
        ->assertTableActionEnabled('approve', $profile)
        ->assertTableActionEnabled('reject', $profile)
        ->assertSee(__('identity::identity.approval_eligibility.hours'))
        ->callTableAction('approve', $profile)
        ->assertNotified($failure);
    Livewire::test(ReviewVendorApplication::class, ['record' => $profile->getRouteKey()])
        ->assertActionEnabled('approve')
        ->assertActionEnabled('reject')
        ->assertSee(__('identity::identity.approval_eligibility.hours'))
        ->callAction('approve')
        ->assertNotified($failure);
    expect($profile->refresh()->approval_status)->toBeInstanceOf(PendingState::class);
})->with(['en', 'ar']);

it('notifies when eligibility becomes incomplete after approval was offered', function (string $locale, string $surface): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(vendorLifecycleActor(['view_any_vendor::profile', 'view_vendor::profile', 'approve_vendor_profile']));
    $profile = eligibleVendorProfile();
    $page = $surface === 'queue'
        ? Livewire::test(ListVendorApprovalQueue::class)
        : Livewire::test(ReviewVendorApplication::class, ['record' => $profile->getRouteKey()]);
    if ($surface === 'queue') {
        $page->assertTableActionEnabled('approve', $profile)->mountTableAction('approve', $profile);
    } else {
        $page->assertActionEnabled('approve')->mountAction('approve');
    }

    $profile->businessHours()->delete();
    if ($surface === 'queue') {
        $page->callMountedTableAction();
    } else {
        $page->callMountedAction();
    }

    $page->assertNotified(Notification::make()
        ->title(__('identity.notifications.vendor_approval_failed'))
        ->body(__('identity::identity.approval_eligibility.hours'))
        ->danger());
    expect($profile->refresh()->approval_status)->toBeInstanceOf(PendingState::class);
})->with(['en', 'ar'])->with(['queue', 'review']);

it('shows successful moderation and removes the vendor from the pending queue', function (string $surface, string $decision): void {
    Event::fake();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(vendorLifecycleActor(['view_any_vendor::profile', 'view_vendor::profile', 'approve_vendor_profile', 'reject_vendor_profile']));
    $profile = eligibleVendorProfile();
    $data = ['rejection_reason_en' => 'Review rejected', 'rejection_reason_ar' => 'تم رفض الطلب'];
    $page = $surface === 'queue'
        ? Livewire::test(ListVendorApprovalQueue::class)->callTableAction($decision, $profile, data: $data)
        : Livewire::test(ReviewVendorApplication::class, ['record' => $profile->getRouteKey()])->callAction($decision, data: $data);

    $page->assertNotified(Notification::make()
        ->title(__('identity.notifications.vendor_'.($decision === 'approve' ? 'approved' : 'rejected')))
        ->success());
    expect($profile->refresh()->approval_status)->toBeInstanceOf($decision === 'approve' ? ApprovedState::class : RejectedState::class);
    if ($surface === 'review') {
        $page->assertRedirect(VendorApprovalQueueResource::getUrl('index'));
    }
    Livewire::test(ListVendorApprovalQueue::class)->assertCanNotSeeTableRecords([$profile]);
})->with(['queue', 'review'])->with(['approve', 'reject']);

it('approves once without requiring a product type and rejects unauthorized approval', function (): void {
    Event::fake();
    $profile = eligibleVendorProfile();
    $unauthorized = User::factory()->create();

    expect(fn () => app(ApproveVendorProfileAction::class)->execute($profile, $unauthorized))
        ->toThrow(HttpException::class);

    $actor = vendorLifecycleActor(['approve_vendor_profile']);
    $approved = app(ApproveVendorProfileAction::class)->execute($profile, $actor);
    $again = app(ApproveVendorProfileAction::class)->execute($profile, $actor);

    expect($approved->approval_status)->toBeInstanceOf(ApprovedState::class)
        ->and($again->getKey())->toBe($approved->getKey())
        ->and($approved->approved_by)->toBe($actor->id)
        ->and($approved->approvedTypes()->count())->toBe(0);
});

it('allows admin approval before phone verification and leaves operations gated afterwards', function (): void {
    Event::fake();
    $profile = eligibleVendorProfile();
    $profile->user->forceFill(['phone_verified_at' => null])->save();
    $actor = vendorLifecycleActor(['approve_vendor_profile']);

    expect(app(VendorApprovalEligibilityService::class)->evaluate($profile)->eligible)->toBeTrue();

    $approved = app(ApproveVendorProfileAction::class)->execute($profile, $actor);

    expect($approved->approval_status)->toBeInstanceOf(ApprovedState::class)
        ->and($approved->user->fresh()->phone_verified_at)->toBeNull();
});

it('keeps grants separate and makes grant revoke suspend and reactivate idempotent', function (): void {
    Event::fake();
    $profile = eligibleVendorProfile();
    $approver = vendorLifecycleActor([
        'approve_vendor_profile',
        'approve_vendor_for_type',
        'revoke_vendor_type',
        'suspend_vendor',
    ]);
    foreach (ProductType::cases() as $productType) {
        foreach (['create', 'update', 'delete', 'publish'] as $operation) {
            Permission::findOrCreate("service.{$operation}.{$productType->value}.own", 'web');
        }
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    app(ApproveVendorProfileAction::class)->execute($profile, $approver);

    expect(fn () => app(RevokeVendorTypeAction::class)->execute($profile, ProductType::Rental, [], $approver))
        ->toThrow(ValidationException::class);

    foreach (ProductType::cases() as $productType) {
        $grant = app(ApproveVendorForTypeAction::class)->execute($profile, $productType, $approver);
        $sameGrant = app(ApproveVendorForTypeAction::class)->execute($profile, $productType, $approver);
        expect($sameGrant->id)->toBe($grant->id);

        $revoked = app(RevokeVendorTypeAction::class)->execute(
            $profile,
            $productType,
            ['en' => 'QA capability review', 'ar' => 'QA capability review AR'],
            $approver,
        );
        $sameRevocation = app(RevokeVendorTypeAction::class)->execute(
            $profile,
            $productType,
            ['en' => 'QA capability review'],
            $approver,
        );
        expect($revoked->id)->toBe($sameRevocation->id);
    }

    $suspended = app(SuspendVendorAction::class)->execute($profile, 'QA lifecycle test', $approver);
    $sameSuspension = app(SuspendVendorAction::class)->execute($profile, 'QA lifecycle test', $approver);
    expect($suspended->approval_status)->toBeInstanceOf(SuspendedState::class)
        ->and($sameSuspension->getKey())->toBe($profile->id);

    $active = app(UnsuspendVendorAction::class)->execute($profile, $approver);
    $sameActive = app(UnsuspendVendorAction::class)->execute($profile, $approver);
    expect($active->approval_status)->toBeInstanceOf(ApprovedState::class)
        ->and($sameActive->getKey())->toBe($profile->id);
});

it('suspends once for an expired critical required document', function (): void {
    Event::fake();
    $profile = eligibleVendorProfile();
    $profile->approval_status = 'approved';
    $profile->approved_at = now();
    $profile->save();

    $document = $profile->documents()
        ->where('doc_type', 'national_id')
        ->firstOrFail();
    $document->update(['expires_at' => today()->subDay(), 'is_critical' => true]);

    $suspended = app(AutoSuspendForExpiredDocAction::class)->execute($document);
    $again = app(AutoSuspendForExpiredDocAction::class)->execute($document);

    expect($suspended->approval_status)->toBeInstanceOf(SuspendedState::class)
        ->and($again->approval_status)->toBeInstanceOf(SuspendedState::class)
        ->and(VendorComplianceEvent::query()
            ->where('document_id', $document->id)
            ->where('event_type', 'auto_suspended')
            ->count())->toBe(1);
});

it('records non-critical expiry once without suspending the vendor', function (): void {
    Event::fake();
    $profile = VendorProfile::factory()->approved()->create();
    $document = VendorDocument::factory()->approved()->create([
        'vendor_profile_id' => $profile->id,
        'doc_type' => 'national_id',
        'expires_at' => today()->subDay(),
        'is_critical' => false,
    ]);

    expect(app(RecordExpiredVendorDocumentAction::class)->execute($document))->toBeTrue()
        ->and(app(RecordExpiredVendorDocumentAction::class)->execute($document))->toBeFalse()
        ->and($profile->refresh()->approval_status)->toBeInstanceOf(ApprovedState::class)
        ->and(VendorComplianceEvent::query()
            ->where('document_id', $document->id)
            ->where('event_type', 'expired')
            ->count())->toBe(1);
});

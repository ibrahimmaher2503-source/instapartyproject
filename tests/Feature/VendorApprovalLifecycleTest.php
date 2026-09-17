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
use App\Modules\Identity\Domain\States\VendorApprovalStatus\SuspendedState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
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

it('accepts required documents through the end of the configured timezone day', function (): void {
    $profile = eligibleVendorProfile();
    $timezone = (string) config('app.timezone', 'UTC');
    $endOfDay = CarbonImmutable::now($timezone)->endOfDay();

    expect(app(VendorApprovalEligibilityService::class)->evaluate($profile, $endOfDay)->eligible)->toBeTrue()
        ->and(app(VendorApprovalEligibilityService::class)->evaluate($profile, $endOfDay->addSecond())->eligible)->toBeFalse();
});

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

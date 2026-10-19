<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\RequestVendorChangesAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\PendingState;
use App\Modules\Identity\Filament\Vendor\Pages\VendorProfileCorrectionPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorProfilePage;
use App\Modules\Shared\Domain\Enums\ChangeRequestItemStatus;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use App\Modules\Shared\Domain\Enums\ChangeRequestStatus;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    Queue::fake();
});

function vendorCorrectionFixture(string $fieldPath = 'business_name.en'): array
{
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('manage_vendor_profile', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->unsetRelation('permissions');

    Role::findOrCreate('vendor', 'vendor');
    $vendor = User::factory()->unverified()->phoneVerified()->asVendor()->create(['preferred_locale' => 'en']);
    $profile = VendorProfile::factory()->pending()->create([
        'user_id' => $vendor->id,
        'business_name' => ['en' => 'Current Business Name', 'ar' => 'الاسم التجاري الحالي'],
    ]);

    $request = app(RequestVendorChangesAction::class)->execute($profile, [[
        'field_path' => $fieldPath,
        'requested_change_en' => 'Use the registered business name',
        'requested_change_ar' => 'استخدم الاسم التجاري المسجل',
    ]], $admin, 'correction-page-'.str()->random(12));

    return [$vendor->fresh(), $profile->fresh(), $request->fresh(), $request->items()->firstOrFail()];
}

it('allows only the owner to open the correction page for an unverified account', function (): void {
    [$vendor, , $request, $item] = vendorCorrectionFixture();

    expect($vendor->hasVerifiedEmail())->toBeFalse()
        ->and($request->status)->toBe(ChangeRequestStatus::Open);

    $this->actingAs($vendor, 'vendor')
        ->get(VendorProfileCorrectionPage::getUrl(panel: 'vendor'))
        ->assertOk()
        ->assertSee('Use the registered business name')
        ->assertSee('Current Business Name');

    $profileResponse = $this->get(VendorProfilePage::getUrl(panel: 'vendor'));
    if (config('app.debug')) {
        $profileResponse->assertOk();
    } else {
        $profileResponse->assertRedirect(Filament::getEmailVerificationPromptUrl());
    }

    $foreign = User::factory()->unverified()->asVendor()->create();
    $this->actingAs($foreign, 'vendor')
        ->get(VendorProfileCorrectionPage::getUrl(panel: 'vendor'))
        ->assertNotFound();

    expect($item->fresh()->item_status)->toBe(ChangeRequestItemStatus::Pending);
});

it('persists the requested correction and resubmits only the current owner request', function (): void {
    [$vendor, $profile, $request, $item] = vendorCorrectionFixture();
    $this->actingAs($vendor, 'vendor');

    $profileResponse = $this->get(VendorProfilePage::getUrl(panel: 'vendor'));
    if (config('app.debug')) {
        $profileResponse->assertOk();
    } else {
        $profileResponse->assertRedirect(Filament::getEmailVerificationPromptUrl());
    }

    Livewire::test(VendorProfileCorrectionPage::class)
        ->set('data.corrections.'.$item->public_id, 'Registered Business Name')
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(__('identity::vendor_correction.saved'));

    expect($profile->fresh()->getTranslation('business_name', 'en'))
        ->toBe('Registered Business Name')
        ->and($profile->fresh()->approval_status)->toBeInstanceOf(PendingState::class)
        ->and($request->fresh()->status)->toBe(ChangeRequestStatus::Resubmitted)
        ->and($item->fresh()->item_status)->toBe(ChangeRequestItemStatus::Addressed);

    $this->get(VendorProfileCorrectionPage::getUrl(panel: 'vendor'))->assertForbidden();
});

it('denies guests and fails closed for a requested field without a supported profile mapping', function (): void {
    $this->get(VendorProfileCorrectionPage::getUrl(panel: 'vendor'))
        ->assertRedirect(Filament::getLoginUrl());

    expect(fn () => vendorCorrectionFixture('unmapped.field'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(ChangeRequest::query()->count())->toBe(0);
});

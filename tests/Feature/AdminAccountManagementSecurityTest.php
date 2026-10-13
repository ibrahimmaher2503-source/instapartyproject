<?php

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Actions\AdminCreateAccountAction;
use App\Modules\Identity\Application\Actions\AdminResendVendorVerificationAction;
use App\Modules\Identity\Application\Actions\AdminSendUserPasswordResetLinkAction;
use App\Modules\Identity\Application\Actions\AdminSyncUserStaffRolesAction;
use App\Modules\Identity\Application\Actions\AdminUpdateUserContactAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\PendingState;
use App\Modules\Identity\Filament\Auth\VendorEmailVerificationPrompt;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('gives an unverified vendor a recoverable resend error for a non-delivering mailer', function (string $mailer) {
    config(['mail.default' => $mailer, 'mail.mailers.custom.transport' => 'log']);
    $vendor = VendorProfile::factory()->create()->user;
    $vendor->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($vendor, 'vendor');
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    Livewire::test(VendorEmailVerificationPrompt::class)
        ->callAction('resendNotification')->assertNotified(__('identity::account_management.delivery_failed'));
    expect($vendor->fresh()->hasVerifiedEmail())->toBeFalse();
})->with(['log', 'custom']);

it('creates a staff account with audited roles and no verified contact bypass', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin, 'web');
    Role::findOrCreate('booking_manager', 'web');
    $staff = app(AdminCreateAccountAction::class)->execute($admin, [
        'name' => 'Staff', 'email' => 'STAFF@example.test', 'phone_e164' => '+201000000999', 'role' => 'booking_manager', 'account_type' => 'staff',
    ]);
    expect($staff->email)->toBe('staff@example.test')->and($staff->hasRole('booking_manager'))->toBeTrue()
        ->and($staff->phone_verified_at)->toBeNull()->and($staff->email_verified_at)->toBeNull();
    expect(DB::table('audit_logs')->where('action', 'managed_account_created')->where('auditable_id', $staff->id)->count())->toBe(1);
});

it('creates customer invitations without assuming marketing or terms consent', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin, 'web');
    $customer = app(AdminCreateAccountAction::class)->execute($admin, [
        'name' => 'Customer', 'email' => 'customer@example.test', 'phone_e164' => '+201000000888', 'account_type' => 'customer',
    ]);
    expect($customer->hasRole('customer'))->toBeTrue()->and($customer->accepted_terms_at)->toBeNull()
        ->and($customer->customerProfile->accepts_marketing)->toBeFalse()->and($customer->phone_verified_at)->toBeNull();
});

it('creates pending vendor invitations through registration without contact or product approval bypass', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin, 'web');
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    $vendor = app(AdminCreateAccountAction::class)->execute($admin, [
        'name' => 'Vendor', 'email' => 'vendor@example.test', 'phone_e164' => '+201000000777', 'account_type' => 'vendor',
        'business_name' => ['en' => 'Vendor business', 'ar' => 'نشاط المورد'], 'business_type' => 'individual',
        'primary_governorate_id' => $governorate->id, 'primary_city_id' => $city->id,
    ]);
    expect($vendor->hasRole('vendor'))->toBeTrue()->and($vendor->hasVerifiedEmail())->toBeFalse()
        ->and($vendor->phone_verified_at)->toBeNull()
        ->and($vendor->vendorProfile->approval_status)->toBeInstanceOf(PendingState::class)
        ->and($vendor->vendorProfile->approvedTypes()->count())->toBe(0);
});

it('records a recoverable failure instead of reporting log mail as password reset delivery', function () {
    config(['mail.default' => 'log']);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $target = User::factory()->create();
    $target->assignRole('admin');
    $this->actingAs($admin, 'web');
    expect(app(AdminSendUserPasswordResetLinkAction::class)->execute($admin, $target))->toBe('delivery_failed');
    expect(DB::table('audit_logs')->where('action', 'user_password_reset_delivery_failed')->count())->toBe(1);
});

it('never manually verifies email when provider delivery is unavailable', function () {
    config(['mail.default' => 'log']);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $vendor = VendorProfile::factory()->create()->user;
    $vendor->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($admin, 'web');
    expect(app(AdminResendVendorVerificationAction::class)->execute($admin, $vendor))->toBeFalse()
        ->and($vendor->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects non-super-admin impostor and revoked creation actors before any new account is persisted', function (string $case) {
    $actor = User::factory()->create();
    $actor->assignRole($case === 'non_super_admin' ? 'admin' : 'super_admin');
    $this->actingAs($actor, 'web');
    if ($case === 'impostor') {
        $other = User::factory()->create();
        $other->assignRole('super_admin');
        $this->actingAs($other, 'web');
    } elseif ($case === 'revoked') {
        $actor->load('roles');
        $actor->roles()->detach();
    }
    $before = User::count();
    expect(fn () => app(AdminCreateAccountAction::class)->execute($actor, [
        'name' => 'Attempt', 'email' => 'attempt@example.test', 'phone_e164' => '+201000000666',
        'account_type' => 'staff', 'role' => 'admin',
    ]))->toThrow(HttpException::class);
    expect(User::count())->toBe($before)->and(DB::table('audit_logs')->where('action', 'managed_account_created')->count())->toBe(0);
})->with(['non_super_admin', 'impostor', 'revoked']);

it('rejects forged city ownership or empty business translations without partial vendor records', function (string $case) {
    $actor = User::factory()->create();
    $actor->assignRole('super_admin');
    $this->actingAs($actor, 'web');
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create([
        'governorate_id' => $case === 'foreign_city' ? Governorate::factory()->create()->id : $governorate->id,
    ]);
    $before = User::count();
    expect(fn () => app(AdminCreateAccountAction::class)->execute($actor, [
        'name' => 'Vendor', 'email' => 'invalid-vendor@example.test', 'phone_e164' => '+201000000665', 'account_type' => 'vendor',
        'business_name' => ['en' => $case === 'empty_en' ? '' : 'Business', 'ar' => $case === 'empty_ar' ? '' : 'نشاط'],
        'business_type' => 'individual', 'primary_governorate_id' => $governorate->id, 'primary_city_id' => $city->id,
    ]))->toThrow(ValidationException::class);
    expect(User::count())->toBe($before)->and(VendorProfile::count())->toBe(0);
})->with(['foreign_city', 'empty_en', 'empty_ar']);

it('does not grant submitted staff roles to a customer invitation', function () {
    $actor = User::factory()->create();
    $actor->assignRole('super_admin');
    $this->actingAs($actor, 'web');
    $customer = app(AdminCreateAccountAction::class)->execute($actor, [
        'name' => 'Customer', 'email' => 'role-attempt@example.test', 'phone_e164' => '+201000000664',
        'account_type' => 'customer', 'role' => 'super_admin',
    ]);
    expect($customer->hasRole('customer'))->toBeTrue()->and($customer->hasRole('super_admin'))->toBeFalse();
});

it('preserves self privileges and forbids converting customer identities to privileged staff', function () {
    $actor = User::factory()->create();
    $actor->assignRole('super_admin');
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $this->actingAs($actor, 'web');
    expect(fn () => app(AdminSyncUserStaffRolesAction::class)->execute($actor, $actor, ['admin']))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(AdminSyncUserStaffRolesAction::class)->execute($actor, $customer, ['super_admin']))
        ->toThrow(AuthorizationException::class);
    expect($actor->fresh()->hasRole('super_admin'))->toBeTrue()->and($customer->fresh()->hasRole('customer'))->toBeTrue();
});

it('rejects password recovery for a mixed privileged-vendor identity and contact edits on the operator', function () {
    $actor = User::factory()->create();
    $actor->assignRole('super_admin');
    $mixed = VendorProfile::factory()->create()->user;
    $mixed->assignRole('admin');
    $this->actingAs($actor, 'web');
    expect(fn () => app(AdminSendUserPasswordResetLinkAction::class)->execute($actor, $mixed))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(AdminUpdateUserContactAction::class)->execute($actor, $actor, 'Changed', 'changed@example.test', '+201000000663'))
        ->toThrow(AuthorizationException::class);
});

it('normalizes changed emails and clears verification only when contact changes', function () {
    $actor = User::factory()->create();
    $actor->assignRole('super_admin');
    $customer = User::factory()->create(['email' => 'owner@example.test']);
    $customer->assignRole('customer');
    $this->actingAs($actor, 'web');

    app(AdminUpdateUserContactAction::class)->execute($actor, $customer, $customer->name, ' OWNER@EXAMPLE.TEST ', $customer->phone_e164);
    expect($customer->fresh()->email)->toBe('owner@example.test')
        ->and($customer->fresh()->email_verified_at)->not->toBeNull();

    app(AdminUpdateUserContactAction::class)->execute($actor, $customer, $customer->name, 'new-owner@example.test', $customer->phone_e164);
    expect($customer->fresh()->email_verified_at)->toBeNull();
});

it('keeps email recovery available for staff and vendors while permitting phone-only customers', function (string $role) {
    $actor = User::factory()->create();
    $actor->assignRole('super_admin');
    $target = $role === 'vendor' ? VendorProfile::factory()->create()->user : User::factory()->create();
    $target->assignRole($role);
    $this->actingAs($actor, 'web');

    if ($role === 'customer') {
        app(AdminUpdateUserContactAction::class)->execute($actor, $target, $target->name, null, $target->phone_e164);
        expect($target->fresh()->email)->toBeNull();
    } else {
        expect(fn () => app(AdminUpdateUserContactAction::class)->execute($actor, $target, $target->name, null, $target->phone_e164))
            ->toThrow(ValidationException::class);
        expect($target->fresh()->email)->not->toBeNull();
    }
})->with(['admin', 'vendor', 'customer']);

it('does not accept custom-named non-delivering transports for recovery actions', function (string $transport) {
    config(['mail.default' => 'custom', 'mail.mailers.custom.transport' => $transport]);
    $actor = User::factory()->create();
    $actor->assignRole('super_admin');
    $staff = User::factory()->create();
    $staff->assignRole('admin');
    $vendor = VendorProfile::factory()->create()->user;
    $vendor->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($actor, 'web');

    expect(app(AdminSendUserPasswordResetLinkAction::class)->execute($actor, $staff))->toBe('delivery_failed')
        ->and(app(AdminResendVendorVerificationAction::class)->execute($actor, $vendor))->toBeFalse();
})->with(['log', 'array', 'failover', 'roundrobin']);

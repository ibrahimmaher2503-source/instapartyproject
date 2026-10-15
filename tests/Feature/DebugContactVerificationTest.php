<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Vendor\Pages\VendorServicesListPage;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Actions\SendOtpAction;
use App\Modules\Identity\Application\Listeners\SendVendorRegistrationEmailVerification;
use App\Modules\Identity\Application\Services\VendorApprovalEligibilityService;
use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use App\Modules\Identity\Domain\Events\VendorRegistered;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\VendorPhoneVerificationPage;
use App\Modules\Identity\Infrastructure\Gateways\SmsOtpGateway;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function (): void {
    config(['app.debug' => true]);
    Http::preventStrayRequests();
});

it('logs newly registered debug customers in without requesting an OTP', function (string $locale): void {
    config(['services.otp.send_on_registration' => true]);
    $this->post("/{$locale}/auth/register", [
        'name' => 'Debug Customer',
        'phone_e164' => '+201012345678',
        'email' => 'debug-customer@example.test',
        'password' => 'SafePassword123!',
        'password_confirmation' => 'SafePassword123!',
        'accepted_terms' => '1',
    ])->assertRedirect("/{$locale}")->assertSessionMissing('auth.verify_phone');

    $user = User::where('email', 'debug-customer@example.test')->firstOrFail();
    $this->assertAuthenticatedAs($user, 'web');
    expect($user->phone_verified_at)->toBeNull()->and($user->email_verified_at)->toBeNull();
    expect(Cache::has('otp_attempts:'.$user->phone_e164))->toBeFalse();
    Http::assertNothingSent();
})->with(['en', 'ar']);

it('skips sending OTPs and consuming attempts even for locked out debug phones', function (): void {
    app()->bind(OtpGatewayInterface::class, SmsOtpGateway::class);
    $phone = '+201012345678';
    Cache::put('otp_lockout:'.$phone, '1', 3600);
    app(SendOtpAction::class)->execute($phone);
    expect(Cache::has('otp_attempts:'.$phone))->toBeFalse();
    Http::assertNothingSent();
});

it('reports debug OTP delivery as skipped', function (): void {
    $this->postJson('/api/v1/phone/otp/send', ['phone_e164' => '+201012345678'])
        ->assertAccepted()->assertJsonPath('data.sent', false);
    Http::assertNothingSent();
});

it('resumes a pending debug registration without displaying a code form', function (string $locale): void {
    $user = User::factory()->create(['phone_e164' => '+201012345678', 'phone_verified_at' => null]);
    $this->withSession(['auth.verify_phone' => $user->phone_e164])->get("/{$locale}/auth/verify")
        ->assertRedirect("/{$locale}")->assertSessionMissing('auth.verify_phone');
    $this->assertAuthenticatedAs($user, 'web');
    expect($user->fresh()->phone_verified_at)->toBeNull();
})->with(['en', 'ar']);

it('reports no contact verification pending after debug vendor API registration', function (): void {
    Notification::fake();
    $governorate = Governorate::factory()->create();
    $this->postJson('/api/v1/register/vendor', [
        'name' => 'Debug Vendor',
        'email' => 'debug-vendor@example.test',
        'phone_e164' => '+201012345678',
        'password' => 'SafePassword123!',
        'password_confirmation' => 'SafePassword123!',
        'business_name' => ['en' => 'Debug Events', 'ar' => 'فعاليات تجريبية'],
        'business_type' => 'individual',
        'primary_governorate_id' => $governorate->id,
    ])->assertCreated()->assertJsonPath('meta.verification_pending', false);
    Notification::assertNothingSent();
});

it('allows the debug phone API without a code or gateway challenge without persisting verification', function (): void {
    app()->bind(OtpGatewayInterface::class, SmsOtpGateway::class);
    $user = User::factory()->unverified()->create(['phone_e164' => '+201012345678', 'phone_verified_at' => null]);
    Cache::put('otp_lockout:'.$user->phone_e164, '1', 3600);

    $this->postJson('/api/v1/phone/verify', ['phone_e164' => $user->phone_e164])
        ->assertOk()->assertJsonStructure(['data' => ['token', 'user']]);
    expect($user->fresh()->phone_verified_at)->toBeNull();
    Http::assertNothingSent();
});

it('does not authenticate unknown phones through the debug bypass', function (): void {
    $this->postJson('/api/v1/phone/verify', ['phone_e164' => '+201012345678'])
        ->assertUnprocessable()->assertJsonValidationErrors('phone_e164');
});

it('requires a valid phone challenge when debug is disabled', function (): void {
    config(['app.debug' => false]);
    app()->bind(OtpGatewayInterface::class, SmsOtpGateway::class);
    $user = User::factory()->create(['phone_e164' => '+201012345678', 'phone_verified_at' => null]);
    $this->postJson('/api/v1/phone/verify', ['phone_e164' => $user->phone_e164])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->postJson('/api/v1/phone/verify', ['phone_e164' => $user->phone_e164, 'code' => '123456'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    expect($user->fresh()->phone_verified_at)->toBeNull();
});

it('skips vendor verification mail and restores the real email state when debug is disabled', function (): void {
    Notification::fake();
    $profile = VendorProfile::factory()->create();
    $user = $profile->user->forceFill(['email_verified_at' => null]);
    $user->save();

    app(SendVendorRegistrationEmailVerification::class)->handle(new VendorRegistered($profile));
    $user->sendEmailVerificationNotification();
    Notification::assertNothingSent();
    expect($user->hasVerifiedEmail())->toBeTrue();

    config(['app.debug' => false]);
    expect($user->hasVerifiedEmail())->toBeFalse();
    app(SendVendorRegistrationEmailVerification::class)->handle(new VendorRegistered($profile));
    Notification::assertCount(1);
});

it('allows approved debug vendors through panel and API contact gates without changing stored verification', function (string $locale): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $profile = VendorProfile::factory()->approved()->create();
    $user = $profile->user->forceFill(['email_verified_at' => null, 'phone_verified_at' => null]);
    $user->save();
    $this->actingAs($user, 'vendor');

    expect(VendorServicesListPage::canAccess())->toBeTrue();
    $this->get(VendorServicesListPage::getUrl(panel: 'vendor'))->assertOk();
    Livewire::test(VendorPhoneVerificationPage::class)->assertSet('isVerified', true);
    expect(app(VendorApprovalEligibilityService::class)->evaluate($profile)->checks['contact'])->toBeTrue();
    Sanctum::actingAs($user, ['*'], 'web');
    $this->getJson('/api/v1/vendor/services')->assertOk();
    expect($user->fresh()->email_verified_at)->toBeNull()->and($user->fresh()->phone_verified_at)->toBeNull();

    config(['app.debug' => false]);
    expect(VendorServicesListPage::canAccess())->toBeFalse();
    $this->getJson('/api/v1/vendor/services')->assertForbidden()->assertJsonPath('errors.0.code', 'email_verification_required');
})->with(['en', 'ar']);

it('keeps vendor approval and password checks active in debug mode', function (): void {
    $profile = VendorProfile::factory()->create(['approval_status' => 'pending']);
    Sanctum::actingAs($profile->user, ['*'], 'web');
    $this->getJson('/api/v1/vendor/services')->assertForbidden()->assertJsonPath('errors.0.code', 'vendor_approval_required');
    $this->postJson('/api/v1/login', ['login' => $profile->user->email, 'password' => 'incorrect'])
        ->assertUnprocessable();
});

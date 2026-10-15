<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\LoginAction;
use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use App\Modules\Identity\Domain\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('customer', 'web');
    Role::findOrCreate('vendor', 'web');
});

it('requires customer phone verification before web or API sign-in in both locales', function (string $locale): void {
    $password = 'CustomerPassword123!';
    $user = User::factory()->asCustomer()->create([
        'email' => "qa-unverified-{$locale}@example.test",
        'password' => $password,
        'phone_verified_at' => null,
    ]);

    $loginPath = "/{$locale}/auth/login";
    $this->from($loginPath)
        ->post($loginPath, ['login' => $user->email, 'password' => $password])
        ->assertRedirect($loginPath)
        ->assertSessionHasErrors('login');
    $this->get($loginPath)->assertOk()->assertSee(__('identity.customer_phone_verification_required'));

    $this->assertGuest('web');
    expect($user->fresh()->last_login_at)->toBeNull()
        ->and($user->tokens()->count())->toBe(0);

    $this->withHeader('Accept-Language', $locale)
        ->postJson('/api/v1/login', ['login' => $user->email, 'password' => $password])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('login')
        ->assertJsonPath('errors.login.0', __('identity.customer_phone_verification_required'));

    expect($user->fresh()->last_login_at)->toBeNull()
        ->and($user->tokens()->count())->toBe(0);
})->with(['en', 'ar']);

it('keeps vendor sign-in available for the separate vendor verification flow', function (): void {
    $password = 'VendorPassword123!';
    $vendor = User::factory()->asVendor()->create([
        'password' => $password,
        'phone_verified_at' => null,
    ]);

    $result = app(LoginAction::class)->execute($vendor->email, $password, useToken: false);

    expect($result['user']->is($vendor))->toBeTrue();
});

it('activates a fresh customer through the session-bound OTP route before account access', function (string $locale): void {
    config(['services.otp.send_on_registration' => false]);

    $gateway = new class implements OtpGatewayInterface
    {
        public ?string $sentPhone = null;

        public ?string $sentCode = null;

        public function send(string $phoneE164, string $code): void
        {
            $this->sentPhone = $phoneE164;
            $this->sentCode = $code;
        }

        public function verify(string $phoneE164, string $code): bool
        {
            return $phoneE164 === $this->sentPhone && $code === $this->sentCode;
        }
    };
    $this->app->instance(OtpGatewayInterface::class, $gateway);

    $phone = $locale === 'en' ? '+201000009901' : '+201000009902';
    $registerPath = "/{$locale}/auth/register";
    $verifyPath = "/{$locale}/auth/verify";
    $this->post($registerPath, [
        'name' => 'QA Activation Customer',
        'phone_e164' => $phone,
        'email' => "qa-activation-{$locale}@example.test",
        'password' => 'SafePassword123!',
        'password_confirmation' => 'SafePassword123!',
        'preferred_locale' => $locale,
        'accepted_terms' => '1',
    ])->assertRedirect($verifyPath)
        ->assertSessionHas('auth.verify_phone', $phone);

    $user = User::query()->where('phone_e164', $phone)->firstOrFail();
    $this->assertGuest('web');
    expect($user->phone_verified_at)->toBeNull();

    $this->from($verifyPath)->post("{$verifyPath}/send")
        ->assertRedirect($verifyPath);
    expect($gateway->sentPhone)->toBe($phone)
        ->and($gateway->sentCode)->toBeString();

    $this->from($verifyPath)->post($verifyPath, ['code' => '999999'])
        ->assertRedirect($verifyPath)
        ->assertSessionHasErrors('code');
    $this->assertGuest('web');
    expect($user->fresh()->phone_verified_at)->toBeNull();

    $this->post($verifyPath, ['code' => $gateway->sentCode])->assertRedirect();
    $this->assertAuthenticatedAs($user, 'web');

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
    $this->get("/{$locale}/account/profile")->assertOk();
})->with(['en', 'ar']);

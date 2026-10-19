<?php

use App\Modules\Identity\Application\Actions\EnableAdminMfaAction;
use App\Modules\Identity\Application\Actions\VerifyAdminMfaAction;
use App\Modules\Identity\Domain\Models\TwoFactorSecret;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Filament\Auth\AdminLogin;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

it('confirms ownership and authenticator possession before storing encrypted admin MFA', function () {
    $admin = User::factory()->create(['password' => 'CurrentAdmin123!']);
    $admin->assignRole('admin');
    $this->actingAs($admin, 'web');
    $secret = (new Google2FA)->generateSecretKey();
    $code = (new Google2FA)->getCurrentOtp($secret);
    $codes = app(EnableAdminMfaAction::class)->execute($admin, $secret, $code, 'CurrentAdmin123!');
    $mfa = TwoFactorSecret::where('user_id', $admin->id)->firstOrFail();
    expect($mfa->getRawOriginal('secret_encrypted'))->not->toBe($secret)
        ->and($mfa->confirmed_at)->not->toBeNull()
        ->and($codes)->toHaveCount(8)
        ->and(Hash::check($codes[0], $mfa->recovery_codes_encrypted[0]))->toBeTrue();
    expect(app(VerifyAdminMfaAction::class)->execute($admin, $code))->toBeFalse();
    expect(app(VerifyAdminMfaAction::class)->execute($admin, $codes[0]))->toBeTrue();
    expect(app(VerifyAdminMfaAction::class)->execute($admin, $codes[0]))->toBeFalse();
});

it('does not authenticate an enrolled admin without a second factor', function () {
    $admin = User::factory()->create(['password' => 'CurrentAdmin123!']);
    $admin->assignRole('admin');
    TwoFactorSecret::create([
        'user_id' => $admin->id, 'secret_encrypted' => (new Google2FA)->generateSecretKey(),
        'recovery_codes_encrypted' => [], 'confirmed_at' => now(),
    ]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(AdminLogin::class)->fillForm([
        'email' => $admin->email, 'password' => 'CurrentAdmin123!', 'mfa_code' => '',
    ])->call('authenticate')->assertHasFormErrors(['email']);
    $this->assertGuest('web');
});

it('rejects reuse after the first verification for an enrolled admin without a stored timestep', function () {
    $admin = User::factory()->create();
    $secret = (new Google2FA)->generateSecretKey();
    TwoFactorSecret::create([
        'user_id' => $admin->id, 'secret_encrypted' => $secret,
        'recovery_codes_encrypted' => [], 'confirmed_at' => now(), 'last_used_timestep' => null,
    ]);
    $code = (new Google2FA)->getCurrentOtp($secret);
    expect(app(VerifyAdminMfaAction::class)->execute($admin, $code))->toBeTrue()
        ->and(app(VerifyAdminMfaAction::class)->execute($admin, $code))->toBeFalse();
});

it('rejects setup with the wrong current password without creating a secret', function () {
    $admin = User::factory()->create(['password' => 'CurrentAdmin123!']);
    $admin->assignRole('admin');
    $this->actingAs($admin, 'web');
    $secret = (new Google2FA)->generateSecretKey();
    expect(fn () => app(EnableAdminMfaAction::class)->execute(
        $admin, $secret, (new Google2FA)->getCurrentOtp($secret), 'WrongPassword!',
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(TwoFactorSecret::count())->toBe(0);
});

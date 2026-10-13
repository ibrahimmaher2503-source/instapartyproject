<?php

use App\Modules\Identity\Application\Actions\ConfirmPasswordResetAction;
use App\Modules\Identity\Application\Actions\LoginAction;
use App\Modules\Identity\Application\Actions\RequestPasswordResetAction;
use App\Modules\Identity\Application\Actions\VerifyPhoneAction;
use App\Modules\Identity\Application\DTOs\ConfirmPasswordResetDTO;
use App\Modules\Identity\Application\DTOs\RequestPasswordResetDTO;
use App\Modules\Identity\Application\Services\OtpRateLimiter;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Infrastructure\Gateways\StubOtpGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

it('does not authenticate administrative accounts through public password or OTP actions', function (string $role) {
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create(['password' => 'weak-test', 'phone_verified_at' => null]);
    $user->assignRole($role);

    foreach ([true, false] as $useToken) {
        expect(fn () => app(LoginAction::class)->execute($user->email, 'weak-test', useToken: $useToken))
            ->toThrow(ValidationException::class);
    }
    $otp = new VerifyPhoneAction(new StubOtpGateway, app(OtpRateLimiter::class));
    expect(fn () => $otp->execute($user->phone_e164, '000000'))->toThrow(ValidationException::class);
    expect($user->fresh()->last_login_at)->toBeNull()
        ->and($user->fresh()->phone_verified_at)->toBeNull()
        ->and($user->tokens()->count())->toBe(0);
})->with(['admin', 'super_admin', 'booking_manager', 'panel_user']);

it('does not issue or consume public recovery tokens for administrative accounts', function (string $role, string $channel) {
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    $identifier = $channel === 'email' ? $user->email : $user->phone_e164;
    app(RequestPasswordResetAction::class)->execute(new RequestPasswordResetDTO($identifier, $channel, 'en'));
    expect(DB::table('password_reset_tokens')->where('email', $identifier)->exists())->toBeFalse();

    $oldPassword = $user->password;
    DB::table('password_reset_tokens')->insert([
        'email' => $identifier, 'token' => Hash::make('test-recovery-token'), 'created_at' => now(),
    ]);
    expect(fn () => app(ConfirmPasswordResetAction::class)->execute(
        new ConfirmPasswordResetDTO($identifier, 'test-recovery-token', 'ReplacementPassword123!'),
    ))->toThrow(RuntimeException::class, 'invalid_or_expired');
    expect($user->fresh()->password)->toBe($oldPassword)
        ->and(DB::table('password_reset_tokens')->where('email', $identifier)->exists())->toBeTrue();
})->with(['admin', 'super_admin', 'booking_manager', 'panel_user'])->with(['email', 'sms']);

it('keeps ordinary customer and vendor password authentication available', function (string $role) {
    $user = User::factory()->create(['password' => 'CustomerPassword123!']);
    $user->assignRole($role);
    $result = app(LoginAction::class)->execute($user->email, 'CustomerPassword123!', useToken: false);
    expect($result['user']->is($user))->toBeTrue()->and($result['token'])->toBeNull();
})->with(['customer', 'vendor']);

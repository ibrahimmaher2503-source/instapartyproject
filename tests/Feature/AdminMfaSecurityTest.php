<?php

use App\Modules\Identity\Application\Actions\EnableAdminMfaAction;
use App\Modules\Identity\Application\Actions\VerifyAdminMfaAction;
use App\Modules\Identity\Application\Services\AdminMfaSession;
use App\Modules\Identity\Domain\Models\TwoFactorSecret;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Filament\Auth\AdminLogin;
use App\Modules\Identity\Filament\Pages\AdminSecurityPage;
use App\Modules\Identity\Http\Middleware\EnsureAdminMfa;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Livewire\Mechanisms\ComponentRegistry;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response;

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

it('rejects setup with the wrong current password without creating a secret', function () {
    $admin = User::factory()->create(['password' => 'CurrentAdmin123!']);
    $admin->assignRole('admin');
    $this->actingAs($admin, 'web');
    $secret = (new Google2FA)->generateSecretKey();
    expect(fn () => app(EnableAdminMfaAction::class)->execute(
        $admin, $secret, (new Google2FA)->getCurrentOtp($secret), 'WrongPassword!',
    ))->toThrow(ValidationException::class);
    expect(TwoFactorSecret::count())->toBe(0);
});

it('invalidates enrolled MFA proof when the password changes', function () {
    $admin = User::factory()->create(['password' => 'OriginalAdminPassword123!']);
    $admin->assignRole('admin');
    $this->actingAs($admin, 'web');
    $secret = (new Google2FA)->generateSecretKey();
    TwoFactorSecret::create(['user_id' => $admin->id, 'secret_encrypted' => $secret,
        'recovery_codes_encrypted' => [], 'confirmed_at' => now()]);
    session()->put('admin_mfa_verified', AdminMfaSession::token($admin, $secret));
    expect(AdminMfaSession::hasProof($admin))->toBeTrue();
    $admin->forceFill(['password' => Hash::make('RotatedAdminPassword123!')])->save();
    expect(AdminMfaSession::hasProof($admin->refresh()))->toBeFalse();
    $request = Request::create('/admin');
    $request->setLaravelSession(app('session.store'));
    expect(app(EnsureAdminMfa::class)->handle($request, fn () => new Response('passed'))->isRedirect())->toBeTrue();
    $this->assertGuest('web');
});

it('retains the optional enrollment policy for an unenrolled administrator', function () {
    $admin = User::factory()->create();
    config(['app.admin_mfa_required' => false]);
    expect(AdminMfaSession::hasProof($admin))->toBeTrue();
    config(['app.admin_mfa_required' => true]);
    expect(AdminMfaSession::hasProof($admin))->toBeFalse();
});

it('rejects an existing password-only admin session after MFA enrollment', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin, 'web');
    TwoFactorSecret::create(['user_id' => $admin->id, 'secret_encrypted' => (new Google2FA)->generateSecretKey(),
        'recovery_codes_encrypted' => [], 'confirmed_at' => now()]);
    $request = Request::create('/admin');
    $request->setLaravelSession(app('session.store'));
    $response = app(EnsureAdminMfa::class)->handle($request, fn () => new Response('passed'));
    expect($response->isRedirect())->toBeTrue();
    $this->assertGuest('web');
});

it('allows an unenrolled administrator to sign out when MFA setup is mandatory', function () {
    config(['app.admin_mfa_required' => true]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin, 'web');
    $this->post('/admin/logout')->assertRedirect();
    $this->assertGuest('web');
});

it('does not authenticate existing weak admin credentials even before MFA enrollment', function () {
    $admin = User::factory()->create(['password' => 'weak-test']);
    $admin->assignRole('admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(AdminLogin::class)->fillForm(['email' => $admin->email, 'password' => 'weak-test'])
        ->call('authenticate')->assertHasFormErrors(['email']);
    $this->assertGuest('web');
});

it('rejects a previously opened real admin Livewire page after its password is rotated', function () {
    config(['app.admin_mfa_required' => false]);
    $admin = User::factory()->create(['password' => 'OriginalAdminPassword123!']);
    $admin->assignRole('admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->withSession([Auth::guard('web')->getName() => $admin->id]);
    $page = $this->get(AdminSecurityPage::getUrl())->assertOk();
    $this->assertAuthenticatedAs($admin, 'web');
    expect(session()->has('password_hash_web'))->toBeTrue();
    preg_match_all('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
    $name = app(ComponentRegistry::class)->getName(AdminSecurityPage::class);
    $snapshot = null;
    foreach ($matches[1] as $encoded) {
        $candidate = json_decode(html_entity_decode($encoded, ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
        if (($candidate['memo']['name'] ?? null) === $name) {
            $snapshot = $candidate;
            break;
        }
    }
    expect($snapshot)->not->toBeNull();
    User::whereKey($admin->id)->update(['password' => Hash::make('RotatedAdminPassword123!')]);
    Auth::forgetGuards();
    $this->withHeader('X-Livewire', 'true')->postJson('/livewire/update', [
        '_token' => csrf_token(),
        'components' => [['snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'updates' => [], 'calls' => []]],
    ])->assertUnauthorized();
    $this->assertGuest('web');
});

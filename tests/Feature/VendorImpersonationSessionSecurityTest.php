<?php

use App\Modules\Identity\Application\Actions\ImpersonateVendorViaWebAction;
use App\Modules\Identity\Application\Services\AdminMfaSession;
use App\Modules\Identity\Domain\Models\TwoFactorSecret;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Http\Middleware\ValidateVendorImpersonationSession;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;

it('ends expired or revoked support sessions and records the terminal event', function (string $condition) {
    config(['app.admin_mfa_required' => false]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Permission::findOrCreate('impersonate_vendor', 'web');
    $admin->givePermissionTo('impersonate_vendor');
    $profile = VendorProfile::factory()->approved()->create();
    $this->actingAs($admin, 'web');
    if ($condition === 'password_rotated') {
        $secret = (new Google2FA)->generateSecretKey();
        TwoFactorSecret::create(['user_id' => $admin->id, 'secret_encrypted' => $secret,
            'recovery_codes_encrypted' => [], 'confirmed_at' => now()]);
        session()->put('admin_mfa_verified', AdminMfaSession::token($admin, $secret));
    }
    app(ImpersonateVendorViaWebAction::class)->execute($profile, $admin, 'Support request', 'طلب دعم الحساب');
    if ($condition === 'expired') {
        $this->travel(31)->minutes();
    } elseif ($condition === 'mfa_enrolled') {
        TwoFactorSecret::create([
            'user_id' => $admin->id, 'secret_encrypted' => (new Google2FA)->generateSecretKey(),
            'recovery_codes_encrypted' => [], 'confirmed_at' => now(),
        ]);
    } elseif ($condition === 'password_rotated') {
        $admin->forceFill(['password' => Hash::make('RotatedAdminPassword123!')])->save();
    } else {
        $profile->user->forceFill(['status' => 'suspended'])->save();
    }
    $request = Request::create('/vendor-portal');
    $request->setLaravelSession(app('session.store'));
    $response = app(ValidateVendorImpersonationSession::class)->handle($request, fn () => new Response('passed'));
    expect($response->isRedirect())->toBeTrue()->and(Auth::guard('vendor')->check())->toBeFalse()
        ->and(session()->has('impersonator_id'))->toBeFalse()->and(Auth::guard('web')->id())
        ->toBe(in_array($condition, ['mfa_enrolled', 'password_rotated'], true) ? null : $admin->id);
    expect(DB::table('audit_logs')->where('action', $condition === 'expired'
        ? 'vendor_web_impersonation_expired' : 'vendor_web_impersonation_access_revoked')->count())->toBe(1);
})->with(['expired', 'revoked', 'mfa_enrolled', 'password_rotated']);

it('registers impersonation enforcement as persistent middleware before authentication', function () {
    $middleware = Filament::getPanel('vendor')->getAuthMiddleware();
    expect(array_search(ValidateVendorImpersonationSession::class, $middleware, true))
        ->toBeLessThan(array_search(Authenticate::class, $middleware, true));
    Filament::getPanel('vendor')->boot();
    expect(app(PersistentMiddleware::class)->getPersistentMiddleware())
        ->toContain(ValidateVendorImpersonationSession::class);
});

it('rejects an active non-panel actor even when impersonation permission was assigned', function () {
    $actor = User::factory()->create();
    $actor->assignRole('customer');
    Permission::findOrCreate('impersonate_vendor', 'web');
    $actor->givePermissionTo('impersonate_vendor');
    $profile = VendorProfile::factory()->approved()->create();
    $this->actingAs($actor, 'web');
    expect(fn () => app(ImpersonateVendorViaWebAction::class)->execute($profile, $actor, 'Support request', 'طلب دعم الحساب'))
        ->toThrow(AuthorizationException::class);
    expect(Auth::guard('vendor')->check())->toBeFalse()->and(session()->has('impersonator_id'))->toBeFalse()
        ->and(DB::table('audit_logs')->where('action', 'vendor_web_impersonation_started')->count())->toBe(0);
});

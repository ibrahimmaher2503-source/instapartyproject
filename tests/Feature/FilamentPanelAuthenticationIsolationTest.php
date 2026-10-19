<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Filament\Auth\IsolatedPanelLogin;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('vendor', 'web');
});

it('keeps guest entry routes inside their panel in both locales', function (string $locale): void {
    $this->withHeader('Accept-Language', $locale)
        ->get('/admin/login')
        ->assertOk();

    $this->withHeader('Accept-Language', $locale)
        ->get('/vendor-portal/login')
        ->assertOk();

    $this->withHeader('Accept-Language', $locale)
        ->get('/admin')
        ->assertRedirect(route('filament.admin.auth.login'));

    $this->withHeader('Accept-Language', $locale)
        ->get('/vendor-portal')
        ->assertRedirect(route('filament.vendor.auth.login'));
})->with(['en', 'ar']);

it('renders localized feedback while panel login is processing', function (string $path, string $locale, string $message): void {
    $this->withHeader('Accept-Language', $locale)
        ->get($path)
        ->assertOk()
        ->assertSee('wire:loading.delay.longest', false)
        ->assertSee('wire:target="authenticate"', false)
        ->assertSee('aria-live="polite"', false)
        ->assertSee($message);
})->with([
    'vendor English' => ['/vendor-portal/login', 'en', 'Signing in is still in progress. If it keeps taking longer, refresh the page to check your sign-in status.'],
    'vendor Arabic' => ['/vendor-portal/login', 'ar', 'ما زال تسجيل الدخول جارياً. إذا استغرق وقتاً أطول، حدّث الصفحة للتحقق من حالة تسجيل الدخول.'],
    'admin English' => ['/admin/login', 'en', 'Signing in is still in progress. If it keeps taking longer, refresh the page to check your sign-in status.'],
    'admin Arabic' => ['/admin/login', 'ar', 'ما زال تسجيل الدخول جارياً. إذا استغرق وقتاً أطول، حدّث الصفحة للتحقق من حالة تسجيل الدخول.'],
]);

it('discards an intended URL belonging to the other panel', function (string $loginUrl, string $foreignUrl): void {
    $this->withSession(['url.intended' => $foreignUrl])
        ->get($loginUrl)
        ->assertOk()
        ->assertSessionMissing('url.intended');
})->with([
    'admin after vendor request' => ['/admin/login', '/vendor-portal'],
    'vendor after admin request' => ['/vendor-portal/login', '/admin'],
]);

it('discards panel intended URLs that do not share the current origin', function (string $intendedUrl): void {
    $this->withSession(['url.intended' => $intendedUrl])
        ->get('/admin/login')
        ->assertOk()
        ->assertSessionMissing('url.intended');
})->with([
    'external host' => 'https://attacker.example/admin/users',
    'protocol-relative host' => '//attacker.example/admin/users',
    'different port' => 'http://localhost:8443/admin/users',
    'embedded credentials' => 'http://user@localhost/admin/users',
]);

it('authenticates each role only in its own panel and redirects to that panel', function (): void {
    $password = 'SafePassword123!';
    $admin = User::factory()->create(['email' => 'qa-admin@example.test', 'password' => $password]);
    $admin->assignRole('admin');
    $vendor = User::factory()->create(['email' => 'qa-vendor@example.test', 'password' => $password]);
    $vendor->assignRole('vendor');

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(IsolatedPanelLogin::class)
        ->fillForm(['email' => $admin->email, 'password' => $password])
        ->call('authenticate')
        ->assertRedirect(Filament::getPanel('admin')->getUrl());

    expect(auth('web')->id())->toBe($admin->id)
        ->and(auth('vendor')->check())->toBeFalse();

    auth('web')->logout();
    $this->app['session']->invalidate();

    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    Livewire::test(IsolatedPanelLogin::class)
        ->fillForm(['email' => $vendor->email, 'password' => $password])
        ->call('authenticate')
        ->assertRedirect(Filament::getPanel('vendor')->getUrl());

    expect(auth('vendor')->id())->toBe($vendor->id)
        ->and(auth('web')->check())->toBeFalse();
});

it('keeps simultaneous admin and vendor sessions on their own login routes', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $vendor = User::factory()->create([
        'email_verified_at' => null,
    ]);
    $vendor->assignRole('vendor');

    auth('web')->login($admin);
    auth('vendor')->login($vendor);

    $this->get('/admin/login')->assertRedirect(Filament::getPanel('admin')->getUrl());
    $this->get('/vendor-portal/login')->assertRedirect(Filament::getPanel('vendor')->getUrl());

    expect(auth('web')->id())->toBe($admin->id)
        ->and(auth('vendor')->id())->toBe($vendor->id);
});

it('removes a legacy cross-panel guard session without logging out the other panel', function (string $guard, string $url, string $role): void {
    $wrongUser = User::factory()->create();
    $wrongUser->assignRole($role);

    auth($guard)->login($wrongUser);

    $this->get($url)->assertOk();

    expect(auth($guard)->check())->toBeFalse();
})->with([
    'vendor stored in admin web guard' => ['web', '/admin/login', 'vendor'],
    'admin stored in vendor guard' => ['vendor', '/vendor-portal/login', 'admin'],
]);

it('rejects credentials from the other panel', function (string $panelId, string $role): void {
    $password = 'SafePassword123!';
    $user = User::factory()->create([
        'email' => "qa-{$role}-cross-panel@example.test",
        'password' => $password,
    ]);
    $user->assignRole($role);

    Filament::setCurrentPanel(Filament::getPanel($panelId));

    Livewire::test(IsolatedPanelLogin::class)
        ->fillForm(['email' => $user->email, 'password' => $password])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(auth(Filament::getPanel($panelId)->getAuthGuard())->check())->toBeFalse();
})->with([
    'vendor credentials on admin' => ['admin', 'vendor'],
    'admin credentials on vendor' => ['vendor', 'admin'],
]);

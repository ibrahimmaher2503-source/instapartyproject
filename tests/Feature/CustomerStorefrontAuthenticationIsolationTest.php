<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('customer', 'web');
});

function storefrontCustomer(string $password = 'SafePassword123!'): User
{
    $user = User::factory()->phoneVerified()->create(['password' => $password]);
    $user->assignRole('customer');

    return $user;
}

it('redirects account guests straight to the matching localized login page', function (string $locale): void {
    $accountPath = '/'.$locale.'/account';

    $this->get($accountPath)
        ->assertRedirect('/'.$locale.'/auth/login')
        ->assertSessionHas('url.intended', fn (string $url): bool => parse_url($url, PHP_URL_PATH) === $accountPath);
})->with(['en', 'ar']);

it('discards a stale admin intended URL after customer login', function (): void {
    $customer = storefrontCustomer();

    $this->withSession(['url.intended' => 'http://localhost/admin/users'])
        ->post('/en/auth/login', ['login' => $customer->email, 'password' => 'SafePassword123!'])
        ->assertRedirect(route('storefront.home'))
        ->assertSessionMissing('url.intended');
});

it('does not redirect customer login to an external intended URL', function (string $intended): void {
    $customer = storefrontCustomer();

    $this->withSession(['url.intended' => $intended])
        ->post('/en/auth/login', ['login' => $customer->email, 'password' => 'SafePassword123!'])
        ->assertRedirect(route('storefront.home'))
        ->assertSessionMissing('url.intended');
})->with([
    'absolute external URL' => 'https://attacker.example/phishing',
    'protocol-relative URL' => '//attacker.example/phishing',
]);

it('does not redirect customer login through a locale path traversal', function (string $intended): void {
    $customer = storefrontCustomer();

    $this->withSession(['url.intended' => $intended])
        ->post('/en/auth/login', ['login' => $customer->email, 'password' => 'SafePassword123!'])
        ->assertRedirect(route('storefront.home'))
        ->assertSessionMissing('url.intended');
})->with([
    'dot segments' => '/en/../admin/users',
    'encoded dot segments' => '/en/%2e%2e/admin/users',
    'backslash segments' => '/en\\..\\admin/users',
]);

it('keeps valid localized storefront destinations after customer login', function (): void {
    $customer = storefrontCustomer();

    $this->withSession(['url.intended' => '/en/account/bookings'])
        ->post('/en/auth/login', ['login' => $customer->email, 'password' => 'SafePassword123!'])
        ->assertRedirect('/en/account/bookings')
        ->assertSessionMissing('url.intended');
});

it('uses the validated intended URL when debug phone verification authenticates', function (): void {
    config(['app.debug' => true]);
    $customer = User::factory()->asCustomer()->create(['phone_verified_at' => null]);

    $this->withSession([
        'auth.verify_phone' => $customer->phone_e164,
        'url.intended' => '/en/account',
    ])->get('/en/auth/verify')
        ->assertRedirect('/en/account')
        ->assertSessionMissing('url.intended');

    $this->assertAuthenticatedAs($customer, 'web');
});

it('keeps the separate vendor session when the customer signs out', function (): void {
    $customer = storefrontCustomer();
    $vendor = User::factory()->create();
    $vendor->assignRole('vendor');

    auth('web')->login($customer);
    auth('vendor')->login($vendor);

    $this->post('/en/auth/logout')->assertRedirect(route('storefront.home'));

    expect(auth('web')->check())->toBeFalse()
        ->and(auth('vendor')->id())->toBe($vendor->id);
});

<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;

function storefrontMobileDrawer(string $html): string
{
    preg_match('/<dialog id="storefront-navigation-drawer".*?<\/dialog>/s', $html, $matches);

    return $matches[0] ?? '';
}

it('shows account and sign-out actions in the authenticated mobile drawer', function (): void {
    $customer = User::factory()->asCustomer()->create();

    $response = $this->actingAs($customer)->get('/en')->assertOk();
    $drawer = storefrontMobileDrawer($response->getContent());

    expect($drawer)->toContain(route('storefront.account.dashboard'))
        ->toContain(route('storefront.auth.logout'))
        ->not->toContain(route('storefront.auth.login'));
});

it('shows only the sign-in action in the guest mobile drawer', function (): void {
    $response = $this->get('/en')->assertOk();
    $drawer = storefrontMobileDrawer($response->getContent());

    expect($drawer)->toContain(route('storefront.auth.login'))
        ->not->toContain(route('storefront.account.dashboard'))
        ->not->toContain(route('storefront.auth.logout'));
});

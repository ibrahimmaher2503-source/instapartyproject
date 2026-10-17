<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;

it('shows customer account and sign out actions in the mobile drawer', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $html = $this->actingAs($customer, 'web')->get('/en/cart')->assertOk()->getContent();

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    $drawer = $xpath->query('//dialog[@id="storefront-navigation-drawer"]')->item(0);
    $drawerText = $drawer?->textContent ?? '';

    expect($drawerText)
        ->toContain(__('account.title'))
        ->toContain(__('storefront.nav.logout'))
        ->not->toContain(__('storefront.nav.login'));

    expect($xpath->query('//dialog[@id="storefront-navigation-drawer"]//a[contains(@href, "/en/account")]')->length)->toBe(1)
        ->and($xpath->query('//dialog[@id="storefront-navigation-drawer"]//form[contains(@action, "/en/auth/logout")]')->length)->toBe(1);
});

it('keeps the sign in action in the mobile drawer for guests', function (): void {
    $html = $this->get('/en/cart')->assertOk()->getContent();

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    $drawer = $xpath->query('//dialog[@id="storefront-navigation-drawer"]')->item(0);
    $drawerText = $drawer?->textContent ?? '';

    expect($drawerText)
        ->toContain(__('storefront.nav.login'))
        ->not->toContain(__('account.title'))
        ->not->toContain(__('storefront.nav.logout'));

    expect($xpath->query('//dialog[@id="storefront-navigation-drawer"]//a[contains(@href, "/en/auth/login")]')->length)->toBe(1)
        ->and($xpath->query('//dialog[@id="storefront-navigation-drawer"]//form[contains(@action, "/en/auth/logout")]')->length)->toBe(0);
});

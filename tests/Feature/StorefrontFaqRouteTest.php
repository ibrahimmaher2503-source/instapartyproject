<?php

declare(strict_types=1);

it('redirects the localized FAQ alias to the canonical CMS page', function (string $locale): void {
    $this->get("/{$locale}/faq")
        ->assertRedirect("/{$locale}/p/faq");

    $this->get("/{$locale}/p/faq")->assertOk();
})->with(['en', 'ar']);

it('returns 404 for an unknown localized CMS page', function (string $locale): void {
    $this->get("/{$locale}/p/not-a-published-page")->assertNotFound();
})->with(['en', 'ar']);

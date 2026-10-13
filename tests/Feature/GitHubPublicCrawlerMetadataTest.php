<?php

declare(strict_types=1);

it('serves deliberate crawl rules with the configured sitemap URL', function (): void {
    config(['app.url' => 'https://marketplace.example.test']);

    $this->get('/robots.txt')->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *', false)
        ->assertSee('Disallow: /admin', false)
        ->assertSee('Disallow: /vendor-portal', false)
        ->assertSee('Disallow: /api', false)
        ->assertSee('Disallow: /en/account', false)
        ->assertSee('Disallow: /ar/auth', false)
        ->assertSee('Sitemap: https://marketplace.example.test/sitemap.xml', false);
});

it('serves a parseable sitemap containing public pages in both locales', function (): void {
    config(['app.url' => 'https://marketplace.example.test']);

    $response = $this->get('/sitemap.xml')->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $xml = simplexml_load_string($response->getContent());
    expect($xml)->not->toBeFalse();
    $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $urls = array_map(static fn ($url): string => (string) $url, $xml->xpath('//s:loc'));

    expect($urls)->toHaveCount(8)
        ->toContain('https://marketplace.example.test/en', 'https://marketplace.example.test/ar')
        ->toContain('https://marketplace.example.test/en/search', 'https://marketplace.example.test/ar/vendors');
    foreach ($urls as $url) {
        expect($url)->not->toContain('/admin', '/vendor-portal', '/account', '/auth');
    }
});

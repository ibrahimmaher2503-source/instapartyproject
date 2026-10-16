<?php

declare(strict_types=1);

use App\Modules\Support\Domain\Models\SupportTicket;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

class EnforcedContactFormCsrfMiddleware extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}

it('accepts a same-origin contact submission with the page CSRF token', function (): void {
    $contactClient = file_get_contents(resource_path('js/storefront.js'));

    expect($contactClient)->toContain("'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.content ?? ''");

    config(['sanctum.stateful' => ['localhost']]);
    config(['sanctum.middleware.validate_csrf_token' => EnforcedContactFormCsrfMiddleware::class]);

    $page = $this->get('/en')->assertOk();
    preg_match('/<meta name="csrf-token" content="([^"]+)"/', $page->getContent(), $matches);

    expect($matches[1] ?? null)->not->toBeNull();

    $this->withHeaders([
        'Origin' => 'http://localhost',
        'Referer' => 'http://localhost/en',
    ])->postJson('/api/v1/customer/support/tickets', [
        'email' => 'visitor@example.test',
        'subject' => 'Missing token',
        'body' => 'This should be rejected.',
    ])->assertStatus(419);

    $this->withHeaders([
        'Origin' => 'http://localhost',
        'Referer' => 'http://localhost/en',
        'X-CSRF-TOKEN' => $matches[1],
    ])->postJson('/api/v1/customer/support/tickets', [
        'email' => 'visitor@example.test',
        'subject' => 'Contact form question',
        'body' => 'Please contact me about an event.',
    ])->assertCreated()
        ->assertJsonStructure(['data' => ['reference']]);

    expect(SupportTicket::query()->count())->toBe(1);
});

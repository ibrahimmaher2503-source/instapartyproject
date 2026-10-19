<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Reviews\Domain\Models\ServiceReview;

it('renders a bounded page of approved service reviews without exposing reviewer details', function (string $locale): void {
    $service = Service::factory()->sale()->published()->create([
        'rating_count' => 0,
        'rating_avg' => 0,
    ]);
    $reviewer = User::factory()->asCustomer()->create([
        'name' => 'Mina Hassan',
        'email' => 'mina.hassan@example.test',
    ]);
    $createdAt = now()->startOfSecond();
    $createdReviews = [];

    foreach (range(0, 5) as $index) {
        $createdReviews[$index] = ServiceReview::factory()->approved()->create([
            'service_id' => $service->id,
            'user_id' => $reviewer->id,
            'rating' => $index === 5 ? 5 : 4,
            'body' => $index === 5 ? 'Approved review <strong>content</strong>' : "Approved review {$index}",
            'created_at' => $createdAt,
        ]);
    }

    ServiceReview::factory()->pending()->create([
        'service_id' => $service->id,
        'body' => 'PENDING REVIEW MUST STAY PRIVATE',
    ]);

    $response = $this->get("/{$locale}/services/{$service->public_id}")->assertOk();
    $html = $response->getContent();

    expect(substr_count($html, 'data-service-review='))->toBe(5)
        ->and($html)->toContain(trans_choice('storefront.service.rating_aria', 6, ['avg' => '4.2', 'count' => 6]))
        ->and($html)->toContain(__('storefront.service.reviews_heading', [], $locale))
        ->toContain(__('storefront.service.reviews_count', ['count' => 6], $locale))
        ->toContain('Approved review')
        ->toContain('&lt;strong&gt;content&lt;/strong&gt;')
        ->toContain('Mina')
        ->toContain('review_cursor=')
        ->not->toContain('PENDING REVIEW MUST STAY PRIVATE')
        ->not->toContain('mina.hassan@example.test')
        ->not->toContain('Mina Hassan');

    preg_match('/href="([^"]*review_cursor=[^"]*)"/', $html, $nextLink);
    expect($nextLink[1] ?? null)->not->toBeNull();
    $nextUrl = html_entity_decode($nextLink[1], ENT_QUOTES | ENT_HTML5);
    $nextPath = parse_url($nextUrl, PHP_URL_PATH);
    $nextQuery = parse_url($nextUrl, PHP_URL_QUERY);

    $nextPage = $this->get($nextPath.'?'.$nextQuery)->assertOk();
    $nextPage->assertSeeText('Approved review 0')
        ->assertDontSeeText('Approved review <strong>content</strong>');
    expect(substr_count($nextPage->getContent(), 'data-service-review='))->toBe(1);
})->with(['en', 'ar']);

it('continues public API review pagination from legacy ISO cursors', function (): void {
    $service = Service::factory()->sale()->published()->create();
    $reviewer = User::factory()->asCustomer()->create();
    $createdAt = now()->startOfSecond();
    $reviews = [];

    foreach (range(0, 15) as $index) {
        $reviews[$index] = ServiceReview::factory()->approved()->create([
            'service_id' => $service->id,
            'user_id' => $reviewer->id,
            'body' => "API review {$index}",
            'created_at' => $createdAt,
        ]);
    }

    $legacyCursor = base64_encode(json_encode([
        'created_at' => $createdAt->toISOString(),
        'id' => $reviews[1]->id,
    ], JSON_THROW_ON_ERROR));

    $this->getJson('/api/v1/public/services/'.$service->public_id.'/reviews?cursor='.urlencode($legacyCursor))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'API review 0');
});

it('returns the API validation envelope for malformed service review cursors', function (string $query): void {
    $service = Service::factory()->sale()->published()->create();

    $this->getJson('/api/v1/public/services/'.$service->public_id.'/reviews?'.$query)
        ->assertUnprocessable()
        ->assertJsonPath('data', null)
        ->assertJsonPath('errors.0.code', 'validation_error')
        ->assertJsonPath('errors.0.field', 'cursor');
})->with([
    'array cursor' => 'cursor%5B%5D=bad',
    'unparseable cursor timestamp' => 'cursor='.urlencode(base64_encode(json_encode([
        'created_at' => 'definitely-not-a-time',
        'id' => 1,
    ], JSON_THROW_ON_ERROR))),
]);

it('shows a localized empty state when no approved service reviews exist', function (string $locale): void {
    $service = Service::factory()->sale()->published()->create();
    ServiceReview::factory()->pending()->create([
        'service_id' => $service->id,
        'body' => 'PENDING REVIEW MUST STAY PRIVATE',
    ]);

    $this->get("/{$locale}/services/{$service->public_id}")
        ->assertOk()
        ->assertSee(__('storefront.service.no_reviews', [], $locale))
        ->assertDontSee('PENDING REVIEW MUST STAY PRIVATE');
})->with(['en', 'ar']);

it('returns validation errors for malformed review cursors instead of reaching the repository', function (string $query): void {
    $service = Service::factory()->sale()->published()->create();

    $this->getJson("/en/services/{$service->public_id}?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('review_cursor');
})->with([
    'array cursor' => 'review_cursor%5B%5D=not-a-cursor',
    'malformed scalar cursor' => 'review_cursor=not-a-cursor',
]);

<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Domain\Models\SavedSearch;
use App\Modules\Identity\Domain\Models\User;

it('filters public search by legacy product_types query keys for every locale and product type', function (string $locale): void {
    config(['scout.driver' => null, 'catalog.digital_services_enabled' => true]);

    foreach (['rental', 'sale', 'digital'] as $type) {
        Service::factory()->{$type}()->published()->create([
            'name' => ['en' => "Search {$type}", 'ar' => "بحث {$type}"],
        ]);
    }

    foreach (['rental', 'sale', 'digital'] as $type) {
        $response = $this->get("/{$locale}/search?product_types%5B0%5D={$type}")->assertOk();
        $services = $response->viewData('services');

        expect($services->total())->toBe(1)
            ->and(collect($services->items())->pluck('product_type')->map(fn ($value) => $value->value)->all())->toBe([$type])
            ->and($response->viewData('filters')['product_type'])->toBe($type);
    }
})->with(['en', 'ar']);

it('normalizes legacy product_types arrays when saving a search and runs the saved filter', function (): void {
    config(['scout.driver' => null]);
    $customer = User::factory()->asCustomer()->create();
    $rental = Service::factory()->rental()->published()->create();
    Service::factory()->sale()->published()->create();

    $this->actingAs($customer, 'web')->post('/en/account/saved-searches', [
        'label' => 'Rental results',
        'filters' => ['product_types' => ['rental']],
    ])->assertRedirect();

    $saved = SavedSearch::query()->where('user_id', $customer->id)->sole();
    expect($saved->filters)->toBe(['product_type' => 'rental']);

    $response = $this->get('/en/search?'.http_build_query($saved->filters))->assertOk();
    expect($response->viewData('services')->total())->toBe(1)
        ->and($response->viewData('services')->items()[0]->is($rental))->toBeTrue();
});

it('retains legacy product-type bookmarks in paginator links', function (): void {
    config(['scout.driver' => null]);
    Service::factory()->count(13)->rental()->published()->create();
    Service::factory()->sale()->published()->create();

    $response = $this->get('/en/search?product_types%5B0%5D=rental&page=2')->assertOk();
    $services = $response->viewData('services');
    parse_str((string) parse_url($services->url(1), PHP_URL_QUERY), $query);

    expect($services->total())->toBe(13)
        ->and($services->currentPage())->toBe(2)
        ->and(collect($services->items())->every(fn (Service $service): bool => $service->product_type->value === 'rental'))->toBeTrue()
        ->and($query['product_types'][0] ?? null)->toBe('rental');
});

it('removes malformed legacy type filters before redirecting so search validation cannot loop', function (string $query): void {
    $response = $this->get('/en/search?q=keep&'.$query)->assertRedirect();
    $location = $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $redirectQuery);

    expect($location)->toContain('/en/search')
        ->and($redirectQuery['q'] ?? null)->toBe('keep')
        ->and(isset($redirectQuery['product_types']))->toBeFalse();

    $this->get($location)->assertOk();
})->with([
    'nonzero numeric key' => 'product_types%5B1%5D=rental',
    'associative key' => 'product_types%5Bfoo%5D=rental',
    'invalid product type' => 'product_types%5B0%5D=unknown',
]);

it('rejects malformed legacy type arrays when saving a search', function (array $productTypes): void {
    $customer = User::factory()->asCustomer()->create();

    $this->actingAs($customer, 'web')->post('/en/account/saved-searches', [
        'label' => 'Malformed search',
        'filters' => ['product_types' => $productTypes],
    ])->assertSessionHasErrors();

    expect(SavedSearch::query()->where('user_id', $customer->id)->exists())->toBeFalse();
})->with([
    'nonzero numeric key' => [['1' => 'rental']],
    'associative key' => [['foo' => 'rental']],
    'multiple values' => [['rental', 'sale']],
    'invalid product type' => [['unknown']],
]);

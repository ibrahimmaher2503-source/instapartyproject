<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Application\Actions\SearchServicesAction;
use App\Modules\Discovery\Application\DTOs\SearchServicesDTO;

it('labels the rating filter below vendor without changing its submitted value', function (string $locale): void {
    $response = $this->get("/{$locale}/search?min_rating=4")->assertOk();
    $response->assertSee($locale === 'ar' ? 'تقييم المورد' : 'Vendor Rating');
    $html = $response->getContent();
    expect(strpos($html, 'for="vendor"'))->toBeLessThan(strpos($html, 'for="min_rating"'))
        ->and(strpos($html, 'for="min_rating"'))->toBeLessThan(strpos($html, 'for="price_min"'));
    $response->assertSee('value="4" selected', false);
})->with(['en', 'ar']);

it('searches both languages and never drops unknown filters', function (): void {
    config(['scout.driver' => 'database']);

    Service::factory()->published()->create([
        'name' => ['en' => 'Birthday Lantern', 'ar' => 'فانوس عيد ميلاد'],
        'short_description' => ['en' => 'Celebration light', 'ar' => 'إضاءة احتفالية'],
        'long_description' => ['en' => 'A bright party lantern', 'ar' => 'فانوس مضيء للحفلات'],
    ]);

    $search = app(SearchServicesAction::class);
    $dto = static fn (?string $query = null, ?string $category = null, ?string $city = null, ?int $priceMin = null): SearchServicesDTO => new SearchServicesDTO(
        query: $query,
        type: null,
        categorySlug: $category,
        occasionCode: null,
        vendorPublicId: null,
        governoratePublicId: null,
        cityPublicId: $city,
        priceMin: $priceMin,
        priceMax: null,
        minRating: null,
        locale: 'ar',
        page: 1,
        perPage: 20,
        sort: null,
    );

    expect($search->execute($dto('Birthday'))->total())->toBe(1)
        ->and($search->execute($dto('فانوس'))->total())->toBe(1)
        ->and($search->execute($dto(category: 'missing-category'))->total())->toBe(0)
        ->and($search->execute($dto(city: 'missing-city'))->total())->toBe(0);

    config(['scout.driver' => 'meilisearch']);

    expect($search->execute($dto(priceMin: PHP_INT_MAX))->total())->toBe(0);
});

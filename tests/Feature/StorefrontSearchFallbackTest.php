<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Application\Actions\SearchServicesAction;
use App\Modules\Discovery\Application\DTOs\SearchServicesDTO;

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

<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Application\Actions\SearchServicesAction;
use App\Modules\Discovery\Application\DTOs\SearchServicesDTO;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Reviews\Application\Actions\ModerateReviewAction;
use App\Modules\Reviews\Application\DTOs\ModerateReviewData;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Models\ServiceReview;

it('labels the rating filter below vendor without changing its submitted value', function (string $locale): void {
    $response = $this->get("/{$locale}/search?min_rating=4")->assertOk();
    $response->assertSee($locale === 'ar' ? 'تقييم المورد' : 'Vendor Rating');
    $html = $response->getContent();
    expect(strpos($html, 'for="vendor"'))->toBeLessThan(strpos($html, 'for="min_rating"'))
        ->and(strpos($html, 'for="min_rating"'))->toBeLessThan(strpos($html, 'for="price_min"'));
    $response->assertSee('value="4" selected', false);
})->with(['en', 'ar']);

it('applies a maximum price and minimum rating to storefront results', function (): void {
    config(['scout.driver' => 'database']);

    $budget = Service::factory()->sale()->published()->create([
        'name' => ['en' => 'Budget Celebration Service', 'ar' => 'خدمة احتفال اقتصادية'],
        'base_price_minor' => 90000,
    ]);
    $midrange = Service::factory()->rental()->published()->create([
        'name' => ['en' => 'Midrange Celebration Service', 'ar' => 'خدمة احتفال متوسطة'],
        'base_price_minor' => 120000,
    ]);
    Service::factory()->digital()->published()->create([
        'name' => ['en' => 'Premium Celebration Service', 'ar' => 'خدمة احتفال فاخرة'],
        'base_price_minor' => 250000,
    ]);

    $moderator = User::factory()->asAdmin()->create();
    $moderate = app(ModerateReviewAction::class);
    foreach ([[$budget, 4], [$midrange, 5]] as [$service, $rating]) {
        $review = ServiceReview::factory()->create([
            'service_id' => $service->id,
            'rating' => $rating,
        ]);
        $moderate->execute($review, new ModerateReviewData(
            toStatus: ModerationStatus::Approved,
            moderatorId: $moderator->id,
        ));
    }

    $response = $this->get('/en/search?price_max=1400&min_rating=4')->assertOk();

    expect($response->viewData('services')->total())->toBe(2);
    $response->assertSee('Budget Celebration Service')
        ->assertSee('Midrange Celebration Service')
        ->assertDontSee('Premium Celebration Service');

    expect($this->get('/en/search?min_rating=5')->assertOk()->viewData('services')->total())->toBe(1);

    expect($this->get('/en/search?price_max=100')->assertOk()->viewData('services')->total())->toBe(0);
});

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

<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Reviews\Domain\Models\ServiceReview;

it('renders approved service review ratings on public service pages and keeps the no-review fallback', function (): void {
    $service = Service::factory()->sale()->published()->create([
        'rating_avg' => 0,
        'rating_count' => 0,
    ]);

    ServiceReview::factory()->approved()->create([
        'service_id' => $service->id,
        'rating' => 4,
        'body' => 'APPROVED SERVICE REVIEW',
    ]);
    ServiceReview::factory()->pending()->create([
        'service_id' => $service->id,
        'rating' => 5,
        'body' => 'PENDING SERVICE REVIEW',
    ]);
    $service->vendor->update(['rating_avg' => 5, 'rating_count' => 1]);

    $this->get(route('storefront.services.show', [
        'locale' => 'en',
        'servicePublicId' => $service->public_id,
    ]))
        ->assertOk()
        ->assertSee('aria-label="Rated 4.0 out of 5, 1 review"', false);

    $serviceWithoutReviews = Service::factory()->sale()->published()->create([
        'rating_avg' => 0,
        'rating_count' => 0,
    ]);
    $serviceWithoutReviews->vendor->update(['rating_avg' => 5, 'rating_count' => 1]);

    $this->get(route('storefront.services.show', [
        'locale' => 'en',
        'servicePublicId' => $serviceWithoutReviews->public_id,
    ]))
        ->assertOk()
        ->assertSee(__('storefront.service.no_reviews', [], 'en'));
});

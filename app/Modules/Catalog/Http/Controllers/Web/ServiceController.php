<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Web;

use App\Modules\Catalog\Application\Actions\BuildServiceEstimateAction;
use App\Modules\Catalog\Http\Requests\ServiceEstimateRequest;
use App\Modules\Identity\Domain\Contracts\PublicVendorPortfolioReader;
use App\Modules\Reviews\Domain\Contracts\PublicServiceReviewReader;
use Illuminate\Contracts\View\View;

class ServiceController
{
    private const PUBLIC_REVIEW_PAGE_SIZE = 5;

    public function __invoke(
        string $servicePublicId,
        ServiceEstimateRequest $request,
        BuildServiceEstimateAction $estimate,
        PublicVendorPortfolioReader $portfolioReader,
        PublicServiceReviewReader $publicReviewReader,
    ): View {
        $data = $estimate->execute($servicePublicId, $request->validated());
        $service = $data['service'];
        $selection = $data['selection'];
        $reviewPage = $publicReviewReader->forService(
            (int) $service->getKey(),
            $request->validated('review_cursor'),
            self::PUBLIC_REVIEW_PAGE_SIZE,
            app()->getLocale(),
        );
        $nextReviewUrl = $reviewPage['next_cursor'] === null
            ? null
            : route('storefront.services.show', [
                ...request()->query(),
                'locale' => app()->getLocale(),
                'servicePublicId' => $service->public_id,
                'review_cursor' => $reviewPage['next_cursor'],
            ]);
        $wizardQuery = array_filter([
            'locale' => app()->getLocale(),
            'service' => $service->public_id,
            'event_starts_at' => $selection['event_starts_at'],
            'event_ends_at' => $selection['event_ends_at'],
            'quantity' => $selection['quantity'],
            'timezone' => $selection['timezone'],
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return view('storefront.services.show', $data + [
            'quantity' => $selection['quantity'],
            'durationHours' => $selection['rental_duration_hours'],
            'vendorPortfolioItems' => $service->vendor?->public_id !== null
                ? $portfolioReader->forApprovedVendor($service->vendor->public_id)
                : [],
            'wizardUrl' => route('storefront.wizard', $wizardQuery),
            'serviceReviews' => $reviewPage['items'],
            'serviceReviewSummary' => $reviewPage['summary'],
            'nextReviewUrl' => $nextReviewUrl,
        ]);
    }
}

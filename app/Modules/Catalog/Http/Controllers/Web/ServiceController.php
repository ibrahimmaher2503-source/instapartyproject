<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Web;

use App\Modules\Catalog\Application\Actions\BuildServiceEstimateAction;
use App\Modules\Catalog\Http\Requests\ServiceEstimateRequest;
use App\Modules\Identity\Domain\Contracts\PublicVendorPortfolioReader;
use App\Modules\Reviews\Domain\Contracts\ServiceReviewRepository;
use Illuminate\Contracts\View\View;

class ServiceController
{
    public function __invoke(
        string $servicePublicId,
        ServiceEstimateRequest $request,
        BuildServiceEstimateAction $estimate,
        PublicVendorPortfolioReader $portfolioReader,
        ServiceReviewRepository $reviews,
    ): View {
        $data = $estimate->execute($servicePublicId, $request->validated());
        $service = $data['service'];
        $selection = $data['selection'];
        $reviewSummary = $reviews->aggregateApprovedForService((int) $service->id);
        $service->setAttribute('rating_avg', $reviewSummary['average']);
        $service->setAttribute('rating_count', $reviewSummary['count']);
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
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Infrastructure\Repositories;

use App\Modules\Reviews\Application\DTOs\CustomerReviewData;
use App\Modules\Reviews\Domain\Contracts\CustomerReviewReader;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Enums\ReviewType;
use App\Modules\Reviews\Domain\Models\ReviewResponse;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;

final class EloquentCustomerReviewReader implements CustomerReviewReader
{
    public function listForCustomer(int $customerId): array
    {
        if ($customerId < 1) {
            return [];
        }

        $locale = app()->getLocale();
        $serviceReviews = ServiceReview::query()
            ->where('user_id', $customerId)
            ->with(['service', 'vendorResponse'])
            ->get()
            ->map(fn (ServiceReview $review): CustomerReviewData => new CustomerReviewData(
                type: ReviewType::Service,
                rating: $review->rating,
                body: $review->body,
                targetName: $review->service?->getTranslation('name', $locale),
                moderationStatus: $review->moderation_status,
                createdAt: $review->created_at,
                vendorResponse: $this->approvedResponse(
                    response: $review->vendorResponse,
                    type: ReviewType::Service,
                    reviewId: (int) $review->getKey(),
                    targetVendorId: $review->service === null ? null : (int) $review->service->vendor_profile_id,
                ),
            ));

        $vendorReviews = VendorReview::query()
            ->where('user_id', $customerId)
            ->with(['vendor', 'vendorResponse'])
            ->get()
            ->map(fn (VendorReview $review): CustomerReviewData => new CustomerReviewData(
                type: ReviewType::Vendor,
                rating: $review->rating,
                body: $review->body,
                targetName: $review->vendor?->getTranslation('business_name', $locale),
                moderationStatus: $review->moderation_status,
                createdAt: $review->created_at,
                vendorResponse: $this->approvedResponse(
                    response: $review->vendorResponse,
                    type: ReviewType::Vendor,
                    reviewId: (int) $review->getKey(),
                    targetVendorId: (int) $review->vendor_profile_id,
                ),
            ));

        return $serviceReviews
            ->concat($vendorReviews)
            ->sortByDesc(fn (CustomerReviewData $review): ?int => $review->createdAt?->getTimestamp())
            ->values()
            ->all();
    }

    private function approvedResponse(
        ?ReviewResponse $response,
        ReviewType $type,
        int $reviewId,
        ?int $targetVendorId,
    ): ?string {
        if (
            $response === null
            || $response->moderation_status !== ModerationStatus::Approved
            || $response->review_type !== $type
            || (int) $response->review_id !== $reviewId
            || $targetVendorId === null
            || (int) $response->vendor_profile_id !== $targetVendorId
        ) {
            return null;
        }

        return $response->body;
    }
}

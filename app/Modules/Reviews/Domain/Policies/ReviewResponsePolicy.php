<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Policies;

use App\Modules\Reviews\Domain\Enums\ReviewType;
use App\Modules\Reviews\Domain\Models\ReviewResponse;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class ReviewResponsePolicy
{
    public function viewAny(Authorizable $actor): bool
    {
        return $this->isAdminPanelUser($actor)
            && ($actor->can('view_any_service::review') || $actor->can('view_any_vendor::review'));
    }

    public function view(Authorizable $actor, ReviewResponse $response): bool
    {
        return $this->isAdminPanelUser($actor)
            && $this->canViewResponseType($actor, $response)
            && $this->matchesReviewOwner($response);
    }

    public function moderate(Authorizable $actor, ReviewResponse $response): bool
    {
        return $this->isAdminPanelUser($actor)
            && $this->canModerateResponseType($actor, $response)
            && $this->matchesReviewOwner($response);
    }

    private function isAdminPanelUser(Authorizable $actor): bool
    {
        $panel = Filament::getCurrentPanel();

        if (! $actor instanceof FilamentUser || $panel === null || $panel->getId() !== 'admin') {
            return false;
        }

        return $actor->canAccessPanel($panel);
    }

    private function canViewResponseType(Authorizable $actor, ReviewResponse $response): bool
    {
        return match ($response->review_type) {
            ReviewType::Service => $actor->can('view_any_service::review'),
            ReviewType::Vendor => $actor->can('view_any_vendor::review'),
        };
    }

    private function canModerateResponseType(Authorizable $actor, ReviewResponse $response): bool
    {
        return match ($response->review_type) {
            ReviewType::Service => $actor->can('update_service::review'),
            ReviewType::Vendor => $actor->can('update_vendor::review'),
        };
    }

    private function matchesReviewOwner(ReviewResponse $response): bool
    {
        $review = match ($response->review_type) {
            ReviewType::Service => ServiceReview::query()->with('service')->find($response->review_id),
            ReviewType::Vendor => VendorReview::query()->find($response->review_id),
        };

        $reviewVendorId = match (true) {
            $review instanceof ServiceReview => $review->service?->vendor_profile_id,
            $review instanceof VendorReview => $review->vendor_profile_id,
            default => null,
        };

        return $reviewVendorId !== null && (int) $reviewVendorId === (int) $response->vendor_profile_id;
    }
}

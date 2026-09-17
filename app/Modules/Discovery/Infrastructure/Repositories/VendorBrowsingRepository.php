<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Infrastructure\Repositories;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-side queries backing the customer vendor-browsing endpoints (F8).
 * Lives in Infrastructure per modules.md — controllers stay thin and no
 * raw SQL leaks into the Http layer.
 */
class VendorBrowsingRepository
{
    /** @return EloquentCollection<int, VendorProfile> */
    public function approvedForFilter(int $limit = 100): EloquentCollection
    {
        return VendorProfile::query()
            ->whereState('approval_status', ApprovedState::class)
            ->whereHas('services', fn (Builder $query) => $query->whereState('status', PublishedState::class))
            ->with('primaryCity')
            ->orderByDesc('rating_avg')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'public_id', 'business_name', 'primary_city_id', 'rating_avg', 'rating_count']);
    }

    /** @return EloquentCollection<int, VendorProfile> */
    public function featuredForStorefront(int $limit = 6): EloquentCollection
    {
        return VendorProfile::query()
            ->whereState('approval_status', ApprovedState::class)
            ->whereHas('services', fn (Builder $query) => $query->whereState('status', PublishedState::class))
            ->with(['primaryCity'])
            ->withCount(['services' => fn (Builder $query) => $query->whereState('status', PublishedState::class)])
            ->orderByDesc('rating_avg')
            ->orderByDesc('rating_count')
            ->limit($limit)
            ->get();
    }

    /** @return CursorPaginator<int, Service> Published services for one vendor, optionally per product type. */
    public function servicesFor(int $vendorProfileId, ?ProductType $type, int $perPage = 20): CursorPaginator
    {
        return Service::query()
            ->where('vendor_profile_id', $vendorProfileId)
            ->whereState('status', PublishedState::class)
            ->when($type !== null, fn ($q) => $q->where('product_type', $type))
            ->with(['category', 'vendor'])
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    /** @return Collection<int, \stdClass> coverage rows joined to cities */
    public function coverageFor(int $vendorProfileId): Collection
    {
        return DB::table('vendor_coverage_areas as vca')
            ->join('cities as c', 'c.id', '=', 'vca.city_id')
            ->where('vca.vendor_profile_id', $vendorProfileId)
            ->orderBy('c.id')
            ->get([
                'c.public_id as city_public_id',
                'c.name as city_name',
                'c.governorate_id',
                'vca.delivery_fee_minor',
                'vca.delivery_fee_currency',
                'vca.min_order_minor',
                'vca.min_order_currency',
            ]);
    }

    /** @return Collection<int, \stdClass> weekly hours rows (day_of_week 0-6) */
    public function weeklyHoursFor(int $vendorProfileId): Collection
    {
        return DB::table('vendor_business_hours')
            ->where('vendor_profile_id', $vendorProfileId)
            ->orderBy('day_of_week')
            ->get(['day_of_week', 'opens_at', 'closes_at']);
    }

    /**
     * Portfolio = gallery media aggregated across the vendor's published
     * services (no dedicated vendor portfolio collection exists in Phase 1).
     *
     * @return array{total_count: int, items: list<array{url: string, service_public_id: string}>}
     */
    public function portfolioFor(int $vendorProfileId, int $limit = 24): array
    {
        $services = Service::query()
            ->where('vendor_profile_id', $vendorProfileId)
            ->whereState('status', PublishedState::class)
            ->get();

        $items = [];
        $total = 0;

        foreach ($services as $service) {
            foreach ($service->getMedia('gallery') as $media) {
                $total++;
                if (count($items) < $limit) {
                    $items[] = ['url' => $media->getUrl(), 'service_public_id' => $service->public_id];
                }
            }
        }

        return ['total_count' => $total, 'items' => $items];
    }

    /** @return array{services_count: int, bookings_count: int} */
    public function statsFor(int $vendorProfileId): array
    {
        return [
            'services_count' => (int) Service::query()
                ->where('vendor_profile_id', $vendorProfileId)
                ->whereState('status', PublishedState::class)
                ->count(),
            'bookings_count' => (int) DB::table('booking_vendors')
                ->where('vendor_profile_id', $vendorProfileId)
                ->where('sub_status', 'completed')
                ->count(),
        ];
    }

    /** @return object|null highest-rated recent approved vendor review */
    public function featuredReviewFor(int $vendorProfileId): ?object
    {
        return DB::table('vendor_reviews as vr')
            ->join('users as u', 'u.id', '=', 'vr.user_id')
            ->where('vr.vendor_profile_id', $vendorProfileId)
            ->where('vr.moderation_status', 'approved')
            ->whereNotNull('vr.body')
            ->orderByDesc('vr.rating')
            ->orderByDesc('vr.id')
            ->first(['vr.rating', 'vr.body', 'vr.created_at', 'u.name as reviewer_name']);
    }
}

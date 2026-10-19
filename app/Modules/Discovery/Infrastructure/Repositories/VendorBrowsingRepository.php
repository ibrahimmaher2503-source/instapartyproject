<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Infrastructure\Repositories;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Shared\Application\Services\PublicMediaUrl;
use App\Modules\Shared\Application\Services\StorefrontText;
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
    public function approvedForFilter(?string $categoryCode = null, ?string $occasionCode = null, int $limit = 100): EloquentCollection
    {
        return VendorProfile::query()
            ->whereState('approval_status', ApprovedState::class)
            ->whereHas('services', fn (Builder $query) => $query
                ->displayable()
                ->when($categoryCode !== null, fn (Builder $services) => $services->whereHas(
                    'category',
                    fn (Builder $categories) => $categories
                        ->where('code', $categoryCode)
                        ->orWhereHas('parent', fn (Builder $parent) => $parent->where('code', $categoryCode)),
                ))
                ->when($occasionCode !== null, fn (Builder $services) => $services->whereHas(
                    'category.occasions',
                    fn (Builder $occasions) => $occasions->where('code', $occasionCode),
                )))
            ->with([
                'primaryCity.governorate', 'primaryGovernorate', 'coverageAreas.city.governorate',
                'services' => fn ($query) => $query
                    ->displayable()
                    ->with(['category.parent', 'category.occasions']),
            ])
            ->orderByDesc('rating_avg')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'public_id', 'business_name', 'primary_city_id', 'primary_governorate_id', 'rating_avg', 'rating_count']);
    }

    /** @return EloquentCollection<int, VendorProfile> */
    public function featuredForStorefront(int $limit = 6): EloquentCollection
    {
        return VendorProfile::query()
            ->whereState('approval_status', ApprovedState::class)
            ->whereHas('services', fn (Builder $query) => $query->displayable())
            ->with(['primaryCity.governorate', 'primaryGovernorate', 'coverageAreas.city.governorate'])
            ->withCount(['services' => fn (Builder $query) => $query->displayable()])
            ->orderByDesc('rating_avg')
            ->orderByDesc('rating_count')
            ->limit($limit)
            ->get();
    }

    /** @return CursorPaginator<int, Service> Published services for one vendor, optionally per product type. */
    public function servicesFor(int $vendorProfileId, ?ProductType $type, int $perPage = 20, ?string $search = null): CursorPaginator
    {
        return Service::query()
            ->where('vendor_profile_id', $vendorProfileId)
            ->displayable()
            ->when($type !== null, fn ($q) => $q->where('product_type', $type))
            ->when(filled($search), fn ($query) => $query->where(fn ($names) => $names
                ->where('name->en', 'like', '%'.$search.'%')
                ->orWhere('name->ar', 'like', '%'.$search.'%')))
            ->with(['category', 'media', 'vendor.primaryCity.governorate', 'vendor.primaryGovernorate', 'vendor.coverageAreas.city.governorate'])
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    /** @return Collection<int, string> */
    public function serviceNamesFor(int $vendorProfileId, string $locale): Collection
    {
        $text = app(StorefrontText::class);

        return Service::query()->where('vendor_profile_id', $vendorProfileId)->displayable()->orderBy('id')
            ->get(['id', 'name'])->map(fn ($service): string => $text->translation($service, 'name', $locale))
            ->filter()->unique()->values();
    }

    /** @return Collection<string, int> Counts over all displayable services, not just the current page. */
    public function serviceTypeCountsFor(int $vendorProfileId): Collection
    {
        return Service::query()
            ->where('vendor_profile_id', $vendorProfileId)
            ->displayable()
            ->selectRaw('product_type, COUNT(*) as service_count')
            ->groupBy('product_type')
            ->orderBy('product_type')
            ->toBase()
            ->pluck('service_count', 'product_type')
            ->map(fn ($count): int => (int) $count);
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
     * Vendor portfolio first, followed by galleries of displayable services.
     *
     * @return array{total_count: int, items: list<array{url: string, thumb: string, service_public_id: ?string}>}
     */
    public function portfolioFor(int $vendorProfileId, int $limit = 24): array
    {
        $services = Service::query()
            ->where('vendor_profile_id', $vendorProfileId)
            ->displayable()
            ->with('media')->orderBy('id')->get();

        $items = [];
        $urls = app(PublicMediaUrl::class);
        $mediaItems = VendorProfile::findOrFail($vendorProfileId)->getMedia('portfolio')
            ->map(fn ($media): array => [$media, null]);
        foreach ($services as $service) {
            foreach ($service->getMedia('gallery') as $media) {
                $mediaItems->push([$media, $service->public_id]);
            }
        }
        foreach ($mediaItems as [$media, $servicePublicId]) {
            $url = $urls->for($media, 'large');
            if ($url !== null && ! isset($items[$url])) {
                $items[$url] = ['url' => $url, 'thumb' => $urls->for($media, 'thumb') ?? $url, 'service_public_id' => $servicePublicId];
            }
        }

        return ['total_count' => count($items), 'items' => array_slice(array_values($items), 0, $limit)];
    }

    /** @return array{services_count: int, bookings_count: int} */
    public function statsFor(int $vendorProfileId): array
    {
        return [
            'services_count' => (int) Service::query()
                ->where('vendor_profile_id', $vendorProfileId)
                ->displayable()
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

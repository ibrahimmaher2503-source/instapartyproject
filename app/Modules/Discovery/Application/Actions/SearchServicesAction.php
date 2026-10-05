<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Application\DTOs\SearchServicesDTO;
use App\Modules\Discovery\Domain\Contracts\SearchRepository;
use App\Modules\Discovery\Domain\Events\ServiceSearchPerformed;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SearchServicesAction
{
    public function __construct(private readonly SearchRepository $searchRepository) {}

    /** @return LengthAwarePaginator<int, Service> */
    public function execute(SearchServicesDTO $dto): LengthAwarePaginator
    {
        // The Scout path below filters/sorts on Meilisearch index attributes
        // (price_minor, is_active, coverage_city_ids…) that do not exist as
        // DB columns. Any other driver (null, database, collection) must use
        // the SQL fallback — under scout:database the index attributes leak
        // into SQL and 500 (SRCH-001).
        if ($dto->eventDate !== null
            || $dto->governoratePublicId !== null
            || $dto->priceMin !== null
            || $dto->priceMax !== null
            || $dto->minRating !== null
            || ! config('catalog.digital_services_enabled', true)
            || config('scout.driver') !== 'meilisearch') {
            return $this->executeDatabaseFallback($dto);
        }

        $builder = Service::search($dto->query ?? '');
        // `is_active` in the index is a pure mirror of the published lifecycle
        // state (Service::toSearchableArray + shouldBeSearchable). Only published
        // services are ever indexed, so this filter can never diverge from the
        // admin-driven published state.
        $builder->where('is_active', true);

        // Hydrate the canonical contract's relations on the models Scout
        // retrieves from the DB, so the result resource never N+1s.
        $builder->query(fn (Builder $query) => $query->with([
            'media',
            'category',
            'vendor.primaryCity',
            'vendor.primaryGovernorate',
            'rentalDetail',
            'saleDetail',
            'digitalDetail',
        ]));

        if ($dto->type !== null) {
            $builder->where('product_type', $dto->type->value);
        }

        if ($dto->categorySlug !== null) {
            $categoryIds = $this->searchRepository->resolveCategoryIds($dto->categorySlug);
            $builder->whereIn('category_id', $categoryIds === [] ? [-1] : $categoryIds);
        }

        if ($dto->occasionCode !== null) {
            $occasionId = $this->searchRepository->resolveOccasionId($dto->occasionCode);
            // An unknown occasion matches nothing — never silently drop the filter.
            $builder->where('occasion_ids', $occasionId ?? -1);
        }

        if ($dto->vendorPublicId !== null) {
            $vendorId = $this->searchRepository->resolveVendorId($dto->vendorPublicId);
            // An unknown vendor matches nothing — never silently drop the filter.
            $builder->where('vendor_id', $vendorId ?? -1);
        }

        if ($dto->cityPublicId !== null) {
            $cityId = $this->searchRepository->resolveCityId($dto->cityPublicId);
            $builder->where('coverage_city_ids', $cityId ?? -1);
        }

        // Scout's orderBy(column, direction) takes two arguments — the engine
        // assembles the `column:direction` Meilisearch spec itself. Passing a
        // combined string here was SRCH-001.
        match ($dto->sort) {
            'price_asc' => $builder->orderBy('price_minor', 'asc'),
            'price_desc' => $builder->orderBy('price_minor', 'desc'),
            'rating_desc' => $builder->orderBy('rating_avg', 'desc'),
            'newest' => $builder->orderBy('id', 'desc'),
            default => null,
        };

        $results = $builder->paginate($dto->perPage, 'page', $dto->page);

        event(new ServiceSearchPerformed(
            query: $dto->query,
            locale: $dto->locale,
            // Drop only NULL (unset) filters — keep explicit 0 values
            // (price_min=0 / min_rating=0 are real filters, not "unset").
            filtersApplied: array_filter([
                'type' => $dto->type?->value,
                'occasion' => $dto->occasionCode,
                'category' => $dto->categorySlug,
                'vendor' => $dto->vendorPublicId,
                'governorate' => $dto->governoratePublicId,
                'city' => $dto->cityPublicId,
                'price_min' => $dto->priceMin,
                'price_max' => $dto->priceMax,
                'min_rating' => $dto->minRating,
            ], static fn ($v): bool => $v !== null),
            resultsCount: $results->total(),
            userId: $this->authenticatedUserId(),
        ));

        return $results;
    }

    /** @return list<int> */
    public function matchingCategoryIds(SearchServicesDTO $dto): array
    {
        return $this->databaseQuery($dto)
            ->distinct()
            ->pluck('category_id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /** @return LengthAwarePaginator<int, Service> */
    private function executeDatabaseFallback(SearchServicesDTO $dto): LengthAwarePaginator
    {
        $builder = $this->databaseQuery($dto)
            ->with(['media', 'category.occasions', 'vendor.primaryCity', 'vendor.primaryGovernorate', 'rentalDetail', 'saleDetail', 'digitalDetail']);

        match ($dto->sort) {
            'price_asc' => $builder->orderBy('base_price_minor'),
            'price_desc' => $builder->orderByDesc('base_price_minor'),
            'rating_desc' => $builder->orderByDesc('rating_avg'),
            'newest' => $builder->orderByDesc('id'),
            default => $builder->orderByDesc('is_featured')->orderByDesc('id'),
        };

        $results = $builder->paginate(
            $dto->perPage,
            ['*'],
            'page',
            $dto->page,
        );

        event(new ServiceSearchPerformed(
            query: $dto->query,
            locale: $dto->locale,
            // Drop only NULL (unset) filters — keep explicit 0 values
            // (price_min=0 / min_rating=0 are real filters, not "unset").
            filtersApplied: array_filter([
                'type' => $dto->type?->value,
                'occasion' => $dto->occasionCode,
                'category' => $dto->categorySlug,
                'vendor' => $dto->vendorPublicId,
                'governorate' => $dto->governoratePublicId,
                'city' => $dto->cityPublicId,
                'price_min' => $dto->priceMin,
                'price_max' => $dto->priceMax,
                'min_rating' => $dto->minRating,
                'event_date' => $dto->eventDate?->toDateString(),
            ], static fn ($v): bool => $v !== null),
            resultsCount: $results->total(),
            userId: $this->authenticatedUserId(),
        ));

        return $results;
    }

    /** @return Builder<Service> */
    private function databaseQuery(SearchServicesDTO $dto): Builder
    {
        $builder = Service::query()->displayable();

        if ($dto->query !== null && trim($dto->query) !== '') {
            $query = '%'.trim($dto->query).'%';
            $builder->where(function ($q) use ($query): void {
                foreach (['en', 'ar'] as $locale) {
                    $q->orWhere("name->{$locale}", 'like', $query)
                        ->orWhere("short_description->{$locale}", 'like', $query)
                        ->orWhere("long_description->{$locale}", 'like', $query);
                }
            });
        }

        if ($dto->type !== null) {
            $builder->where('product_type', $dto->type->value);
        }

        if ($dto->categorySlug !== null) {
            $categoryIds = $this->searchRepository->resolveCategoryIds($dto->categorySlug);
            $builder->whereIn('category_id', $categoryIds === [] ? [-1] : $categoryIds);
        }

        if ($dto->occasionCode !== null) {
            $occasionId = $this->searchRepository->resolveOccasionId($dto->occasionCode);
            $builder->whereHas('category.occasions', fn ($query) => $query->whereKey($occasionId ?? -1));
        }

        if ($dto->vendorPublicId !== null) {
            $vendorId = $this->searchRepository->resolveVendorId($dto->vendorPublicId);
            $builder->where('vendor_profile_id', $vendorId ?? -1);
        }

        if ($dto->governoratePublicId !== null) {
            $governorateId = $this->searchRepository->resolveGovernorateId($dto->governoratePublicId) ?? -1;
            $builder->whereHas('vendor', fn ($query) => $query
                ->where('primary_governorate_id', $governorateId)
                ->orWhereHas('coverageAreas.city', fn ($cities) => $cities->where('governorate_id', $governorateId)));
        }

        if ($dto->cityPublicId !== null) {
            $cityId = $this->searchRepository->resolveCityId($dto->cityPublicId) ?? -1;
            $builder->whereHas('vendor', fn ($query) => $query
                ->where('primary_city_id', $cityId)
                ->orWhereHas('coverageAreas', fn ($coverage) => $coverage->where('city_id', $cityId)));
        }

        if ($dto->priceMin !== null) {
            $builder->where('base_price_minor', '>=', $dto->priceMin);
        }

        if ($dto->priceMax !== null) {
            $builder->where('base_price_minor', '<=', $dto->priceMax);
        }

        if ($dto->minRating !== null) {
            $builder->where('rating_avg', '>=', $dto->minRating);
        }

        $startsAt = $dto->eventStartsAt ?? $dto->eventDate?->startOfDay();
        $endsAt = $dto->eventStartsAt !== null
            ? ($dto->eventEndsAt ?? $dto->eventStartsAt)
            : $dto->eventDate?->endOfDay();
        if ($startsAt !== null && $endsAt !== null) {
            $builder->where(static function (Builder $query) use ($startsAt, $endsAt): void {
                $query
                    ->where('product_type', '!=', ProductType::Rental->value)
                    ->orWhereDoesntHave('availabilityBlocks', static fn (Builder $blocks) => $blocks
                        ->where('starts_at', '<=', $endsAt)
                        ->where('ends_at', '>=', $startsAt));
            });
        }

        return $builder;
    }

    private function authenticatedUserId(): ?int
    {
        $id = auth()->id();

        return $id === null ? null : (int) $id;
    }
}

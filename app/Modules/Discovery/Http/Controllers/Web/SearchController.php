<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Web;

use App\Modules\Catalog\Application\Actions\ListPublicCategoriesAction;
use App\Modules\Catalog\Application\Actions\ListPublicOccasionsAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Discovery\Application\Actions\SearchServicesAction;
use App\Modules\Discovery\Application\DTOs\SearchServicesDTO;
use App\Modules\Discovery\Domain\Contracts\PackageRecommendationReader;
use App\Modules\Discovery\Infrastructure\Repositories\VendorBrowsingRepository;
use App\Modules\Geography\Domain\Contracts\GeographyRepository;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SearchController
{
    public function __invoke(
        Request $request,
        SearchServicesAction $search,
        ListPublicCategoriesAction $categories,
        ListPublicOccasionsAction $occasions,
        VendorBrowsingRepository $vendors,
        GeographyRepository $geography,
        PackageRecommendationReader $packages,
    ): View {
        $planner = $request->boolean('planner');
        $filters = $request->validate([
            'planner' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:255'],
            'product_type' => ['nullable', Rule::enum(ProductType::class)],
            'category_slug' => ['nullable', 'string', 'max:120'],
            'occasion' => [$planner ? 'required' : 'nullable', 'string', 'max:120'],
            'package' => ['nullable', 'string', 'max:26'],
            'vendor' => ['nullable', 'string', 'max:26'],
            'governorate_public_id' => [$planner ? 'required' : 'required_with:city_public_id', 'nullable', 'string', 'max:26', 'exists:governorates,public_id'],
            'city_public_id' => [$planner ? 'required' : 'nullable', 'string', 'max:26', 'exists:cities,public_id'],
            'event_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'event_starts_at' => [$planner ? 'required' : 'nullable', 'date', 'after:now'],
            'event_ends_at' => ['nullable', 'date', 'after:event_starts_at'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0', 'gte:price_min'],
            'min_rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'sort' => ['nullable', Rule::in(['price_asc', 'price_desc', 'rating_desc', 'newest'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $selectedGovernorate = null;
        if (isset($filters['city_public_id'])) {
            $city = $geography->findCityByPublicId((string) $filters['city_public_id']);
            $selectedGovernorate = $geography->findGovernorateByPublicId((string) $filters['governorate_public_id']);

            if ($city === null || $selectedGovernorate === null || $city->governorate_id !== $selectedGovernorate->id) {
                throw ValidationException::withMessages([
                    'city_public_id' => __('storefront.wizard.validation.city_mismatch'),
                ]);
            }
        } elseif (isset($filters['governorate_public_id'])) {
            $selectedGovernorate = $geography->findGovernorateByPublicId((string) $filters['governorate_public_id']);
        }

        $eventDate = $filters['event_date']
            ?? (isset($filters['event_starts_at']) ? substr((string) $filters['event_starts_at'], 0, 10) : null);

        $type = isset($filters['product_type'])
            ? ProductType::from($filters['product_type'])
            : null;
        if ($type === ProductType::Digital && ! config('catalog.digital_services_enabled', true)) {
            unset($filters['product_type']);
            $type = null;
        }
        $categoryList = $categories->execute();
        $categoryContext = isset($filters['category_slug'])
            ? $categoryList->firstWhere('code', $filters['category_slug'])
            : null;
        $occasionList = $occasions->execute();
        $occasionContext = isset($filters['occasion'])
            ? $occasionList->firstWhere('code', $filters['occasion'])
            : null;
        $packageFilterOptions = collect($packages->publishedForFilter(app()->getLocale()));
        $occasionPackageOptions = $occasionContext === null
            ? collect()
            : $packageFilterOptions
                ->where('occasion_code', $occasionContext->code)
                ->values();
        $selectedPackage = isset($filters['package'])
            ? $occasionPackageOptions->firstWhere('public_id', $filters['package'])
            : null;
        if (isset($filters['package']) && $selectedPackage === null) {
            unset($filters['package']);
        }
        $vendorList = $vendors->approvedForFilter(
            $filters['category_slug'] ?? null,
            $filters['occasion'] ?? null,
        );
        if (isset($filters['vendor']) && ! $vendorList->contains('public_id', $filters['vendor'])) {
            unset($filters['vendor']);
        }
        $vendorFilterOptions = $vendors->approvedForFilter()
            ->map(fn ($vendor): array => [
                'public_id' => $vendor->public_id,
                'label' => trim($vendor->getTranslation('business_name', app()->getLocale(), useFallbackLocale: true)
                    .($vendor->primaryCity === null ? '' : ' · '.$vendor->primaryCity->getTranslation('name', app()->getLocale(), useFallbackLocale: true))),
                'combinations' => $vendor->services
                    ->filter(fn ($service): bool => $service->category !== null)
                    ->map(fn ($service): array => [
                        'categories' => array_values(array_filter([
                            $service->category->code,
                            $service->category->parent?->code,
                        ])),
                        'occasions' => $service->category->occasions->pluck('code')->values()->all(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
        $searchDto = new SearchServicesDTO(
            query: $filters['q'] ?? null,
            type: $type,
            categorySlug: $filters['category_slug'] ?? null,
            occasionCode: $filters['occasion'] ?? null,
            vendorPublicId: $filters['vendor'] ?? null,
            governoratePublicId: $filters['governorate_public_id'] ?? null,
            cityPublicId: $filters['city_public_id'] ?? null,
            priceMin: isset($filters['price_min'])
                ? Money::of((string) $filters['price_min'], 'EGP')->getMinorAmount()->toInt()
                : null,
            priceMax: isset($filters['price_max'])
                ? Money::of((string) $filters['price_max'], 'EGP')->getMinorAmount()->toInt()
                : null,
            minRating: isset($filters['min_rating']) ? (float) $filters['min_rating'] : null,
            locale: app()->getLocale(),
            page: max(1, (int) ($filters['page'] ?? 1)),
            perPage: 12,
            sort: $filters['sort'] ?? null,
            eventDate: $eventDate !== null
                ? CarbonImmutable::createFromFormat('!Y-m-d', $eventDate, 'UTC')
                : null,
            eventStartsAt: isset($filters['event_starts_at'])
                ? CarbonImmutable::parse((string) $filters['event_starts_at'])->utc()
                : null,
            eventEndsAt: isset($filters['event_ends_at'])
                ? CarbonImmutable::parse((string) $filters['event_ends_at'])->utc()
                : null,
        );
        $results = $search->execute($searchDto);

        $results->withQueryString();

        $matchingCategoryIds = $search->matchingCategoryIds($searchDto);
        $availablePackages = $packages->publishedForCategories(
            app()->getLocale(),
            $matchingCategoryIds,
            $filters['occasion'] ?? null,
        );
        if ($selectedPackage !== null) {
            $availablePackages = array_values(array_filter(
                $availablePackages,
                fn (array $package): bool => $package['public_id'] === $selectedPackage['public_id'],
            ));
        }

        return view('storefront.search', [
            'services' => $results,
            'categories' => $categoryList,
            'categoryContext' => $categoryContext,
            'occasions' => $occasionList,
            'occasionContext' => $occasionContext,
            'vendors' => $vendorList,
            'vendorFilterOptions' => $vendorFilterOptions,
            'packageFilterOptions' => $packageFilterOptions
                ->map(fn (array $package): array => [
                    'public_id' => $package['public_id'],
                    'name' => $package['name'],
                    'occasion_code' => $package['occasion_code'],
                ])
                ->all(),
            'occasionPackageOptions' => $occasionPackageOptions->all(),
            'selectedPackage' => $selectedPackage,
            'governorates' => $geography->activeGovernorates(),
            'cities' => $selectedGovernorate === null
                ? collect()
                : $geography->listCitiesByGovernorate((int) $selectedGovernorate->id),
            'filters' => $filters,
            'recommendedPackages' => $availablePackages,
        ]);
    }
}

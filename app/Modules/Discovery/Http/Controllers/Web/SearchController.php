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
            'vendor' => ['nullable', 'string', 'max:26'],
            'governorate_public_id' => [$planner ? 'required' : 'nullable', 'string', 'max:26'],
            'city_public_id' => [$planner ? 'required' : 'nullable', 'string', 'max:26', 'exists:cities,public_id'],
            'event_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'event_starts_at' => [$planner ? 'required' : 'nullable', 'date', 'after:now'],
            'event_ends_at' => [$planner ? 'required' : 'nullable', 'date', 'after:event_starts_at'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0', 'gte:price_min'],
            'min_rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'sort' => ['nullable', Rule::in(['price_asc', 'price_desc', 'rating_desc', 'newest'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($planner) {
            $city = $geography->findCityByPublicId((string) $filters['city_public_id']);
            $governorate = $geography->findGovernorateByPublicId((string) $filters['governorate_public_id']);

            if ($city === null || $governorate === null || $city->governorate_id !== $governorate->id) {
                throw ValidationException::withMessages([
                    'city_public_id' => __('storefront.wizard.validation.city_mismatch'),
                ]);
            }
        }

        $eventDate = $filters['event_date']
            ?? (isset($filters['event_starts_at']) ? substr((string) $filters['event_starts_at'], 0, 10) : null);

        $type = isset($filters['product_type'])
            ? ProductType::from($filters['product_type'])
            : null;
        $categoryList = $categories->execute();
        $categoryContext = isset($filters['category_slug'])
            ? $categoryList->firstWhere('code', $filters['category_slug'])
            : null;
        $occasionList = $occasions->execute();
        $occasionContext = isset($filters['occasion'])
            ? $occasionList->firstWhere('code', $filters['occasion'])
            : null;
        $results = $search->execute(new SearchServicesDTO(
            query: $filters['q'] ?? null,
            type: $type,
            categorySlug: $filters['category_slug'] ?? null,
            occasionCode: $filters['occasion'] ?? null,
            vendorPublicId: $filters['vendor'] ?? null,
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
        ));

        $results->withQueryString();

        $categoryIds = $categoryContext === null
            ? []
            : array_values($categoryList
                ->filter(fn ($category): bool => $category->id === $categoryContext->id
                    || $category->parent_id === $categoryContext->id)
                ->map(fn ($category): int => (int) $category->getKey())
                ->all());

        return view('storefront.search', [
            'services' => $results,
            'categories' => $categoryList,
            'categoryContext' => $categoryContext,
            'occasions' => $occasionList,
            'occasionContext' => $occasionContext,
            'vendors' => $vendors->approvedForFilter(),
            'cities' => $geography->searchCities(''),
            'filters' => $filters,
            'recommendedPackages' => ($planner || $occasionContext !== null)
                ? array_slice($packages->publishedForContext(app()->getLocale(), $filters['occasion'] ?? null), 0, 4)
                : $packages->publishedForCategories(app()->getLocale(), $categoryIds),
        ]);
    }
}

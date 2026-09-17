@extends('storefront.layouts.app')

@php
    $selectedType = $filters['product_type'] ?? null;
    $selectedCategory = $filters['category_slug'] ?? null;
    $selectedOccasion = $filters['occasion'] ?? null;
    $selectedVendor = $filters['vendor'] ?? null;
    $selectedCity = $filters['city_public_id'] ?? null;
    $selectedStartsAt = $filters['event_starts_at'] ?? null;
    $selectedEndsAt = $filters['event_ends_at'] ?? null;
    $selectedDate = $filters['event_date'] ?? ($selectedStartsAt ? substr((string) $selectedStartsAt, 0, 10) : null);
    $selectedGovernorate = $filters['governorate_public_id'] ?? null;
    $plannerContext = ! empty($filters['planner']);
    $recommendedPackages = $recommendedPackages ?? [];
    $categoryContext = $categoryContext ?? null;
    $occasionContext = $occasionContext ?? null;
    $storefrontText = app(App\Modules\Shared\Application\Services\StorefrontText::class);
    $priceMin = $filters['price_min'] ?? null;
    $priceMax = $filters['price_max'] ?? null;
    $selectedRating = $filters['min_rating'] ?? null;
    $context = $categoryContext ?: $occasionContext;
    $categoryName = $context ? $storefrontText->translation($context, 'name') : '';
    $categoryDescription = $context ? $storefrontText->translation($context, 'description') : '';
    $searchState = request()->except(['q', 'page']);
    $clearSearchUrl = route('storefront.search', $searchState);
    $clearFiltersUrl = route('storefront.search');
    $filterUrl = fn (string $key): string => route('storefront.search', request()->except([$key, 'page']));
    $selectedCategoryModel = $selectedCategory ? $categories->firstWhere('code', $selectedCategory) : null;
    $selectedOccasionModel = $selectedOccasion ? $occasions->firstWhere('code', $selectedOccasion) : null;
    $selectedCityModel = $selectedCity ? $cities->firstWhere('public_id', $selectedCity) : null;
    $selectedVendorModel = $selectedVendor ? $vendors->firstWhere('public_id', $selectedVendor) : null;
    $activeFilters = collect([
        'q' => ! empty($filters['q']) ? ['label' => '“'.$filters['q'].'”', 'url' => $clearSearchUrl] : null,
        'product_type' => $selectedType ? ['label' => __('storefront.search.type_'.$selectedType), 'url' => $filterUrl('product_type')] : null,
        'category_slug' => $selectedCategoryModel ? ['label' => $storefrontText->translation($selectedCategoryModel, 'name'), 'url' => $filterUrl('category_slug')] : null,
        'occasion' => $selectedOccasionModel ? ['label' => $storefrontText->translation($selectedOccasionModel, 'name'), 'url' => $filterUrl('occasion')] : null,
        'city_public_id' => $selectedCityModel ? ['label' => $storefrontText->translation($selectedCityModel, 'name'), 'url' => $filterUrl('city_public_id')] : null,
        'event_date' => ! empty($filters['event_date']) ? ['label' => $filters['event_date'], 'url' => $filterUrl('event_date')] : null,
        'vendor' => $selectedVendorModel ? ['label' => $storefrontText->translation($selectedVendorModel, 'business_name'), 'url' => $filterUrl('vendor')] : null,
        'price_min' => $priceMin !== null ? ['label' => __('storefront.search.filter_price_min_chip', ['value' => $priceMin]), 'url' => $filterUrl('price_min')] : null,
        'price_max' => $priceMax !== null ? ['label' => __('storefront.search.filter_price_max_chip', ['value' => $priceMax]), 'url' => $filterUrl('price_max')] : null,
        'min_rating' => $selectedRating !== null ? ['label' => __('storefront.search.filter_min_rating_chip', ['n' => $selectedRating]), 'url' => $filterUrl('min_rating')] : null,
    ])->filter();
    $hasNonQueryFilters = $activeFilters->keys()->contains(fn (string $key): bool => $key !== 'q');
@endphp

@section('title', ($categoryName ?: __('storefront.search.title')).' · '.__('storefront.common.site_name'))

@section('content')
    <div class="sf-search-page bg-[var(--sf-ivory)]">
        <section class="sf-search-hero sf-category-discovery-hero border-b border-white/10 bg-secondary-900 text-white">
            <div class="sf-category-hero__inner mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="sf-category-hero__content">
                    @if ($context)
                        <nav class="sf-category-breadcrumb" aria-label="{{ __('storefront.nav.home') }}">
                            <a href="{{ route('storefront.home') }}">{{ __('storefront.nav.home') }}</a>
                            <span aria-hidden="true">/</span>
                            <span aria-current="page">{{ $categoryName }}</span>
                        </nav>
                    @endif

                    <p class="sf-category-hero__eyebrow">{{ __('storefront.nav.search') }}</p>
                    <h1>{{ $categoryName ?: __('storefront.search.title') }}</h1>
                    @if ($categoryDescription || ! $context)
                        <p class="sf-category-hero__description">{{ $categoryDescription ?: __('storefront.home.hero.sub') }}</p>
                    @endif

                    <form action="{{ route('storefront.search') }}" method="GET" class="sf-category-search" role="search">
                        <label class="sr-only" for="catalog-search">{{ __('storefront.search.placeholder') }}</label>
                        <svg class="sf-category-search__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="10.75" cy="10.75" r="5.75" />
                            <path stroke-linecap="round" d="m15.25 15.25 4.25 4.25" />
                        </svg>
                        <input id="catalog-search" name="q" value="{{ $filters['q'] ?? '' }}" type="search" placeholder="{{ __('storefront.search.placeholder') }}" autocomplete="off">
                        @if (! empty($filters['q']))
                            <a href="{{ $clearSearchUrl }}" class="sf-category-search__clear" aria-label="{{ __('storefront.common.close') }}">×</a>
                        @endif
                        @foreach ($searchState as $name => $value)
                            @if (is_scalar($value) && $value !== '')
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <button type="submit" class="sf-button sf-button--navy">{{ __('storefront.common.search') }}</button>
                    </form>
                </div>

                @if ($context && $recommendedPackages !== [])
                    <section class="sf-category-packages" aria-labelledby="category-packages-heading">
                        <h2 id="category-packages-heading">{{ __('storefront.home.packages.eyebrow') }}</h2>
                        <div class="sf-category-packages__rail">
                            @foreach ($recommendedPackages as $package)
                                <a href="{{ route('storefront.search', array_filter(['category_slug' => $selectedCategory, 'occasion' => $selectedOccasion])) }}" class="sf-category-package-card">
                                    @if ($package['hero_url'])
                                        <img src="{{ $package['hero_url'] }}" alt="" width="320" height="240" loading="lazy">
                                    @endif
                                    <span class="sf-category-package-card__body">
                                        <strong>{{ $package['name'] }}</strong>
                                        @if ($package['min_budget'] || $package['max_budget'])
                                            <small>
                                                {{ $package['min_budget'] && $package['max_budget']
                                                    ? __('storefront.home.packages.budget_range', ['min' => $package['min_budget'], 'max' => $package['max_budget']])
                                                    : __('storefront.home.packages.budget_from', ['amount' => $package['min_budget'] ?? $package['max_budget']]) }}
                                            </small>
                                        @endif
                                        <span>{{ __('storefront.home.packages.explore') }} <b aria-hidden="true">→</b></span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </section>

        <div class="sf-marketplace-shell mx-auto grid max-w-7xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[17rem_minmax(0,1fr)] lg:gap-8 lg:px-8 lg:py-10">
            <aside id="search-filters" class="sf-search-filters" popover="auto" aria-label="{{ __('storefront.search.filters_sidebar_heading') }}">
                <form action="{{ route('storefront.search') }}" method="GET">
                    @if ($plannerContext)
                        <input type="hidden" name="planner" value="1">
                    @endif
                    <div class="sf-filter-panel__header">
                        <h2>{{ __('storefront.search.filters_sidebar_heading') }}</h2>
                        <div class="flex items-center gap-2">
                            <a href="{{ $clearFiltersUrl }}">{{ __('storefront.search.clear_filters') }}</a>
                            <button type="button" class="sf-filter-panel__close" popovertarget="search-filters" popovertargetaction="hide" aria-label="{{ __('storefront.common.close') }}">×</button>
                        </div>
                    </div>

                    <div class="sf-filter-groups">
                        <div class="sf-filter-group">
                            <label for="product_type">{{ __('storefront.search.filter_type') }}</label>
                            <select id="product_type" name="product_type" class="sf-field">
                                <option value="">{{ __('storefront.search.type_all') }}</option>
                                <option value="rental" @selected($selectedType === 'rental')>{{ __('storefront.search.type_rental') }}</option>
                                <option value="sale" @selected($selectedType === 'sale')>{{ __('storefront.search.type_sale') }}</option>
                                <option value="digital" @selected($selectedType === 'digital')>{{ __('storefront.search.type_digital') }}</option>
                            </select>
                        </div>

                        <div class="sf-filter-group">
                            <label for="category_slug">{{ __('storefront.search.filter_category') }}</label>
                            <select id="category_slug" name="category_slug" class="sf-field">
                                <option value="">{{ __('storefront.search.filter_all') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->code }}" @selected($selectedCategory === $category->code)>{{ $storefrontText->translation($category, 'name') }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sf-filter-group">
                            <label for="occasion">{{ __('storefront.search.filter_occasion') }}</label>
                            <select id="occasion" name="occasion" class="sf-field">
                                <option value="">{{ __('storefront.search.filter_all') }}</option>
                                @foreach ($occasions as $occasion)
                                    <option value="{{ $occasion->code }}" @selected($selectedOccasion === $occasion->code)>{{ $storefrontText->translation($occasion, 'name') }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sf-filter-group">
                            <label for="city_public_id">{{ __('storefront.search.filter_city') }}</label>
                            <select id="city_public_id" name="city_public_id" class="sf-field">
                                <option value="">{{ __('storefront.search.filter_all_cities') }}</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city->public_id }}" @selected($selectedCity === $city->public_id)>{{ $storefrontText->translation($city, 'name') }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sf-filter-group">
                            <label for="event_date">{{ __('storefront.search.filter_date') }}</label>
                            <span class="sf-localized-date" data-localized-date>
                                <input id="event_date" name="event_date" value="{{ $selectedDate }}" type="date" lang="{{ app()->getLocale() }}" min="{{ now('UTC')->toDateString() }}" class="sf-field" data-localized-date-input>
                                <span aria-hidden="true" data-localized-date-placeholder>{{ __('storefront.common.date_placeholder') }}</span>
                            </span>
                        </div>

                        <div class="sf-filter-group">
                            <label for="vendor">{{ __('storefront.search.filter_vendor') }}</label>
                            <select id="vendor" name="vendor" class="sf-field">
                                <option value="">{{ __('storefront.search.filter_vendor_all') }}</option>
                                @foreach ($vendors as $vendor)
                                    <option value="{{ $vendor->public_id }}" @selected($selectedVendor === $vendor->public_id)>
                                        {{ $storefrontText->translation($vendor, 'business_name') }}
                                        @if ($vendor->primaryCity)
                                            · {{ $storefrontText->translation($vendor->primaryCity, 'name') }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <fieldset class="sf-filter-group">
                            <legend>{{ __('storefront.search.filter_price') }}</legend>
                            <div class="sf-price-fields">
                                <label for="price_min">
                                    <span>{{ __('storefront.search.filter_price_min') }}</span>
                                    <input id="price_min" name="price_min" value="{{ $priceMin }}" type="number" min="0" inputmode="numeric" class="sf-field">
                                </label>
                                <label for="price_max">
                                    <span>{{ __('storefront.search.filter_price_max') }}</span>
                                    <input id="price_max" name="price_max" value="{{ $priceMax }}" type="number" min="0" inputmode="numeric" class="sf-field">
                                </label>
                            </div>
                        </fieldset>

                        <div class="sf-filter-group">
                            <label for="min_rating">{{ __('storefront.search.filter_rating') }}</label>
                            <select id="min_rating" name="min_rating" class="sf-field">
                                <option value="">{{ __('storefront.search.filter_all') }}</option>
                                @foreach ([4, 3, 2, 1] as $rating)
                                    <option value="{{ $rating }}" @selected((string) $selectedRating === (string) $rating)>{{ __('storefront.search.filter_min_rating_chip', ['n' => $rating]) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @if (! empty($filters['q']))
                        <input type="hidden" name="q" value="{{ $filters['q'] }}">
                    @endif
                    @if ($selectedStartsAt)
                        <input type="hidden" name="event_starts_at" value="{{ $selectedStartsAt }}">
                    @endif
                    @if ($selectedEndsAt)
                        <input type="hidden" name="event_ends_at" value="{{ $selectedEndsAt }}">
                    @endif
                    @if ($selectedGovernorate)
                        <input type="hidden" name="governorate_public_id" value="{{ $selectedGovernorate }}">
                    @endif
                    @if (! empty($filters['sort']))
                        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                    @endif
                    <button type="submit" class="sf-button sf-button--secondary w-full">{{ __('storefront.search.apply_filters') }}</button>
                </form>
            </aside>

            <section class="sf-search-results min-w-0" aria-labelledby="search-results-heading">
                <div class="sf-results-toolbar">
                    <div>
                        <p role="status" aria-live="polite">{{ trans_choice('storefront.search.results_count', $services->total(), ['count' => $services->total()]) }}</p>
                        <h2 id="search-results-heading">{{ $categoryContext ? __('storefront.category.services_in', ['name' => $categoryName]) : __('storefront.search.filters_sidebar_label') }}</h2>
                    </div>

                    <div class="sf-results-toolbar__actions">
                        <button type="button" class="sf-mobile-filter-button" popovertarget="search-filters">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4" />
                            </svg>
                            {{ __('storefront.search.filters') }}
                        </button>

                        <form action="{{ route('storefront.search') }}" method="GET" class="sf-sort-form">
                            @foreach (request()->except(['sort', 'page']) as $name => $value)
                                @if (is_scalar($value) && $value !== '')
                                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <label for="sort">{{ __('storefront.search.sort.label') }}</label>
                            <select id="sort" name="sort" class="sf-field">
                                <option value="">{{ __('storefront.search.sort_relevance') }}</option>
                                <option value="price_asc" @selected(($filters['sort'] ?? null) === 'price_asc')>{{ __('storefront.search.sort.price_asc') }}</option>
                                <option value="price_desc" @selected(($filters['sort'] ?? null) === 'price_desc')>{{ __('storefront.search.sort.price_desc') }}</option>
                                <option value="rating_desc" @selected(($filters['sort'] ?? null) === 'rating_desc')>{{ __('storefront.search.sort.rating_desc') }}</option>
                                <option value="newest" @selected(($filters['sort'] ?? null) === 'newest')>{{ __('storefront.search.sort_newest') }}</option>
                            </select>
                            <button type="submit">{{ __('storefront.search.sort.label') }}</button>
                        </form>
                    </div>
                </div>

                @if ($activeFilters->isNotEmpty())
                    <div class="sf-active-filters" aria-label="{{ __('storefront.search.active_filters') }}">
                        <span>{{ __('storefront.search.active_filters') }}</span>
                        @foreach ($activeFilters as $filter)
                            <a href="{{ $filter['url'] }}">
                                {{ $filter['label'] }}
                                <span aria-hidden="true">×</span>
                                <span class="sr-only">{{ __('storefront.search.remove_filter', ['filter' => $filter['label']]) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                @if ($plannerContext)
                    <section class="sf-context-recommendations mt-7 rounded-[1.5rem] border border-secondary-100 bg-secondary-50/70 p-5 sm:p-6" aria-labelledby="planner-recommendations-heading">
                        <div class="max-w-2xl">
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-secondary-700">{{ __('storefront.home.packages.eyebrow') }}</p>
                            <h2 id="planner-recommendations-heading" class="mt-2 text-xl font-extrabold text-ink-900">{{ __('storefront.search.context_heading') }}</h2>
                            <p class="mt-2 text-sm leading-6 text-ink-600">{{ __('storefront.search.context_body') }}</p>
                        </div>
                        @if ($recommendedPackages !== [])
                            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                @foreach ($recommendedPackages as $package)
                                    <a href="{{ route('storefront.search', array_filter(['occasion' => $selectedOccasion, 'city_public_id' => $selectedCity, 'event_date' => $selectedDate])) }}" class="group overflow-hidden rounded-2xl border border-secondary-100 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-surface">
                                        @if ($package['hero_url'])
                                            <img src="{{ $package['hero_url'] }}" alt="" class="h-32 w-full object-cover" loading="lazy">
                                        @endif
                                        <span class="block space-y-2 p-4">
                                            <strong class="block text-base text-ink-900 group-hover:text-secondary-700">{{ $package['name'] }}</strong>
                                            <span class="line-clamp-2 block text-xs leading-5 text-ink-500">{{ $package['description'] }}</span>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-5 rounded-xl border border-dashed border-secondary-200 bg-white px-4 py-4 text-sm leading-6 text-ink-600">{{ __('storefront.search.context_empty') }}</p>
                        @endif
                    </section>
                @endif

                @if ($services->isEmpty())
                    <div class="sf-search-empty">
                        <div aria-hidden="true">⌕</div>
                        <h3>{{ __('storefront.search.empty_title') }}</h3>
                        <p>{{ __('storefront.search.empty_body') }}</p>
                        <div class="sf-search-empty__actions">
                            @if (! empty($filters['q']))
                                <a href="{{ $clearSearchUrl }}" class="sf-button sf-button--navy">{{ __('storefront.search.clear_search') }}</a>
                            @endif
                            @if ($hasNonQueryFilters)
                                <a href="{{ $clearFiltersUrl }}" class="sf-button sf-button--secondary">{{ __('storefront.search.empty_action') }}</a>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="sf-catalog-grid">
                        @foreach ($services as $service)
                            <x-storefront.service-card :service="$service" />
                        @endforeach
                    </div>

                    @if ($services->lastPage() > 1)
                        @php
                            $pageNumbers = collect([1, $services->currentPage() - 1, $services->currentPage(), $services->currentPage() + 1, $services->lastPage()])
                                ->filter(fn (int $page): bool => $page >= 1 && $page <= $services->lastPage())
                                ->unique()
                                ->sort()
                                ->values();
                            $previousPageNumber = null;
                        @endphp
                        <nav class="sf-search-pagination" aria-label="{{ __('storefront.common.pagination') }}">
                            @if ($services->onFirstPage())
                                <span aria-disabled="true">{{ __('storefront.search.page_prev') }}</span>
                            @else
                                <a href="{{ $services->previousPageUrl() }}" rel="prev">{{ __('storefront.search.page_prev') }}</a>
                            @endif

                            @foreach ($pageNumbers as $pageNumber)
                                @if ($previousPageNumber !== null && $pageNumber - $previousPageNumber > 1)
                                    <span class="sf-search-pagination__ellipsis" aria-hidden="true">…</span>
                                @endif
                                @if ($pageNumber === $services->currentPage())
                                    <span aria-current="page">{{ $pageNumber }}</span>
                                @else
                                    <a href="{{ $services->url($pageNumber) }}">{{ $pageNumber }}</a>
                                @endif
                                @php($previousPageNumber = $pageNumber)
                            @endforeach

                            @if ($services->hasMorePages())
                                <a href="{{ $services->nextPageUrl() }}" rel="next">{{ __('storefront.search.page_next') }}</a>
                            @else
                                <span aria-disabled="true">{{ __('storefront.search.page_next') }}</span>
                            @endif
                        </nav>
                    @endif
                @endif
            </section>
        </div>
    </div>
@endsection

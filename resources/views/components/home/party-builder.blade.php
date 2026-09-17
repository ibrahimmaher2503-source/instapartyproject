@props(['occasions' => [], 'categories' => [], 'cities' => []])

@php($storefrontText = app(App\Modules\Shared\Application\Services\StorefrontText::class))

<section class="sf-party-builder" aria-labelledby="party-builder-heading">
    <img class="sf-party-builder__scene" src="{{ asset(config('storefront.planner_scene')) }}" alt="" aria-hidden="true" loading="lazy">
    <div class="sf-party-builder__shell">
        <div class="sf-party-builder__title">
            <p class="sf-section-eyebrow">{{ __('storefront.home.planner.eyebrow') }}</p>
            <h2 id="party-builder-heading">{{ __('storefront.home.planner.heading') }}</h2>
        </div>
        <form class="sf-party-builder__form" action="{{ url('/'.app()->getLocale().'/search') }}" method="GET">
            <label for="home-planner-occasion">
                <span>{{ __('storefront.home.planner.occasion_label') }}</span>
                <select id="home-planner-occasion" name="occasion">
                    <option value="">{{ __('storefront.home.planner.occasion_placeholder') }}</option>
                    @foreach (collect($occasions)->take(8) as $occasion)
                        <option value="{{ $occasion->code }}" @selected(request('occasion') === $occasion->code)>{{ $storefrontText->translation($occasion, 'name') }}</option>
                    @endforeach
                </select>
            </label>
            <label for="home-planner-city">
                <span>{{ __('storefront.home.planner.location_label') }}</span>
                <select id="home-planner-city" name="city_public_id">
                    <option value="">{{ __('storefront.search.filter_all_cities') }}</option>
                    @foreach (collect($cities) as $city)
                        <option value="{{ $city->public_id }}" @selected(request('city_public_id') === $city->public_id)>{{ $storefrontText->translation($city, 'name') }}</option>
                    @endforeach
                </select>
            </label>
            <label for="home-planner-date">
                <span>{{ __('storefront.home.planner.date_label') }}</span>
                <span class="sf-localized-date" data-localized-date>
                    <input id="home-planner-date" name="event_date" type="date" lang="{{ app()->getLocale() }}" value="{{ request('event_date') }}" min="{{ now('UTC')->toDateString() }}" data-localized-date-input>
                    <span aria-hidden="true" data-localized-date-placeholder>{{ __('storefront.common.date_placeholder') }}</span>
                </span>
            </label>
            <label for="home-planner-category">
                <span>{{ __('storefront.search.filter_category') }}</span>
                <select id="home-planner-category" name="category">
                    <option value="">{{ __('storefront.search.filter_all') }}</option>
                    @foreach (collect($categories)->take(8) as $category)
                        <option value="{{ $category->code }}" @selected(request('category') === $category->code)>{{ $storefrontText->translation($category, 'name') }}</option>
                    @endforeach
                </select>
            </label>
            <button class="sf-party-builder__search" type="submit">{{ __('storefront.home.planner.search') }}</button>
        </form>
        <a class="sf-party-builder__filters" href="{{ url('/'.app()->getLocale().'/search') }}">{{ __('storefront.home.planner.filters') }}</a>
    </div>
</section>

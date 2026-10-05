@props(['occasions' => [], 'governorates' => []])

@php($storefrontText = app(App\Modules\Shared\Application\Services\StorefrontText::class))

<section class="sf-party-builder" aria-labelledby="party-builder-heading">
    <img class="sf-party-builder__scene" src="{{ asset(config('storefront.planner_scene')) }}" alt="" aria-hidden="true" loading="lazy">
    <div class="sf-party-builder__shell">
        <div class="sf-party-builder__title">
            <p class="sf-section-eyebrow">{{ __('storefront.home.planner.eyebrow') }}</p>
            <h2 id="party-builder-heading">{{ __('storefront.home.planner.heading') }}</h2>
        </div>
        <form class="sf-party-builder__form" action="{{ url('/'.app()->getLocale().'/search') }}" method="GET" data-search-geography>
            <label for="home-planner-occasion">
                <span>{{ __('storefront.home.planner.occasion_label') }}</span>
                <select id="home-planner-occasion" name="occasion">
                    <option value="">{{ __('storefront.home.planner.occasion_placeholder') }}</option>
                    @foreach (collect($occasions)->take(8) as $occasion)
                        <option value="{{ $occasion->code }}" @selected(request('occasion') === $occasion->code)>{{ $storefrontText->translation($occasion, 'name') }}</option>
                    @endforeach
                </select>
            </label>
            <label for="home-planner-governorate">
                <span>{{ __('storefront.search.filter_governorate') }}</span>
                <select id="home-planner-governorate" name="governorate_public_id" data-search-governorate>
                    <option value="">{{ __('storefront.search.filter_all_governorates') }}</option>
                    @foreach (collect($governorates) as $governorate)
                        <option value="{{ $governorate->public_id }}">{{ $storefrontText->translation($governorate, 'name') }}</option>
                    @endforeach
                </select>
            </label>
            <label for="home-planner-city">
                <span>{{ __('storefront.home.planner.location_label') }}</span>
                <select id="home-planner-city" name="city_public_id" data-search-city disabled>
                    <option value="">{{ __('storefront.search.filter_all_cities') }}</option>
                </select>
            </label>
            <label for="home-planner-starts-at">
                <span>{{ __('storefront.home.planner.date_label') }}</span>
                <span class="sf-localized-date" data-localized-date>
                    <input id="home-planner-starts-at" name="event_starts_at" type="datetime-local" lang="{{ app()->getLocale() }}" value="{{ request('event_starts_at') }}" min="{{ now()->format('Y-m-d\\TH:i') }}" data-localized-date-input>
                    <span aria-hidden="true" data-localized-date-placeholder>{{ __('storefront.common.datetime_placeholder') }}</span>
                </span>
            </label>
            <button class="sf-party-builder__search" type="submit">{{ __('storefront.home.planner.search') }}</button>
        </form>
        <a class="sf-party-builder__filters" href="{{ url('/'.app()->getLocale().'/search') }}">{{ __('storefront.home.planner.filters') }}</a>
    </div>
</section>

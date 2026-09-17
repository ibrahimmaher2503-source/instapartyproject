@extends('storefront.layouts.app')

@section('title', __('storefront.home.vendors_strip.heading').' · '.__('storefront.common.site_name'))

@section('content')
    <div class="sf-vendors-page bg-[var(--sf-ivory)]">
        <section class="bg-secondary-900 text-white">
            <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-12 lg:px-8">
                <p class="text-sm font-bold uppercase tracking-[0.22em] text-secondary-200">{{ __('storefront.home.vendors_strip.eyebrow') }}</p>
                <h1 class="mt-3 max-w-3xl text-3xl font-extrabold sm:text-4xl">{{ __('storefront.home.vendors_strip.heading') }}</h1>
                <p class="mt-4 max-w-2xl text-base leading-7 text-white/70">{{ __('storefront.vendor.index_intro') }}</p>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8" aria-labelledby="vendors-heading">
            <form action="{{ url('/'.app()->getLocale().'/vendors') }}" method="get" role="search" class="mb-8 flex flex-col gap-3 rounded-2xl border border-ink-200 bg-white p-4 sm:flex-row sm:items-end">
                <label class="min-w-0 flex-1 text-sm font-semibold text-ink-800">{{ __('storefront.vendor.search_label') }}
                    <input name="q" type="search" maxlength="100" value="{{ $search }}" placeholder="{{ __('storefront.vendor.search_placeholder') }}" class="mt-2 w-full rounded-xl border border-ink-200 px-4 py-3 text-ink-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary-600">
                </label>
                <label class="text-sm font-semibold text-ink-800">{{ __('storefront.vendor.sort_label') }}
                    <select name="sort" class="mt-2 w-full rounded-xl border border-ink-200 px-4 py-3 text-ink-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary-600">
                        <option value="rating" @selected($sort === 'rating')>{{ __('storefront.vendor.sort_rating') }}</option>
                        <option value="newest" @selected($sort === 'newest')>{{ __('storefront.vendor.sort_newest') }}</option>
                    </select>
                </label>
                <button type="submit" class="sf-button sf-button--secondary justify-center">{{ __('storefront.vendor.search_button') }}</button>
            </form>
            <div class="flex items-end justify-between gap-4 border-b border-ink-200 pb-5">
                <div>
                    <p class="text-sm font-semibold text-ink-500">{{ trans_choice('storefront.vendor.results_count', (int) $vendors->total(), ['count' => $vendors->total()]) }}</p>
                    <h2 id="vendors-heading" class="mt-1 text-2xl font-extrabold tracking-[-0.03em] text-ink-900">{{ __('storefront.home.vendors_strip.view_all') }}</h2>
                </div>
                @if ($search !== '')<a href="{{ url('/'.app()->getLocale().'/vendors') }}" class="text-sm font-bold text-secondary-700 hover:text-secondary-900">{{ __('storefront.vendor.clear_search') }}</a>@endif
            </div>

            @if ($vendors->isEmpty())
                <div class="mt-8 rounded-[1.5rem] border border-dashed border-ink-300 bg-white px-6 py-14 text-center text-sm text-ink-600">{{ __('storefront.vendor.empty_results') }}</div>
            @else
                <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($vendors as $vendor)
                        <x-storefront.vendor-card :vendor="$vendor" />
                    @endforeach
                </div>
                @if ($vendors->lastPage() > 1)
                    <nav class="mt-10" aria-label="{{ __('storefront.vendor.pagination_label') }}">{{ $vendors->links() }}</nav>
                @endif
            @endif
        </section>
    </div>
@endsection

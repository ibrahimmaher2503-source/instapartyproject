@extends('storefront.layouts.app')

@section('title', $package['name'].' · '.__('storefront.common.site_name'))

@section('content')
    <main class="bg-[var(--sf-ivory)]" data-package-details>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
            <nav class="sf-service-breadcrumb" aria-label="{{ __('storefront.common.breadcrumb') }}">
                <a href="{{ route('storefront.home') }}">{{ __('storefront.nav.home') }}</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('storefront.search') }}">{{ __('storefront.nav.search') }}</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">{{ $package['name'] }}</span>
            </nav>

            <div class="mt-7 overflow-hidden rounded-[1.75rem] border border-secondary-100 bg-white shadow-surface lg:grid lg:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]">
                <div class="min-h-72 bg-secondary-50">
                    @if ($package['reference_card'] ?? [])
                        <x-home.package-photo :package="$package" />
                    @elseif ($package['hero_url'])
                        <img src="{{ $package['hero_url'] }}" alt="{{ $package['name'] }}" class="h-full min-h-72 w-full object-cover" fetchpriority="high">
                    @else
                        <div class="flex h-full min-h-72 items-center justify-center px-8 text-center">
                            <span class="max-w-lg text-4xl font-extrabold tracking-[-0.04em] text-secondary-800 sm:text-5xl">{{ $package['name'] }}</span>
                        </div>
                    @endif
                </div>

                <div class="flex flex-col justify-center p-6 sm:p-8 lg:p-10">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-secondary-700">{{ __('storefront.home.packages.eyebrow') }}</p>
                    <h1 class="mt-3 text-3xl font-extrabold tracking-[-0.04em] text-ink-900 sm:text-4xl">{{ $package['name'] }}</h1>
                    <p class="mt-5 text-base leading-8 text-ink-600">{{ $package['description'] }}</p>

                    @if ($package['min_budget'] || $package['max_budget'])
                        <div class="mt-7 rounded-2xl bg-secondary-50 p-4" data-package-budget data-package-price-kind="{{ ($package['fixed_budget'] ?? null) ? 'fixed' : ($package['min_budget'] && $package['max_budget'] ? 'range' : 'from') }}">
                            <span class="block text-xs font-bold text-ink-500">{{ __('storefront.package.budget_label') }}</span>
                            <strong class="mt-1 block text-lg text-secondary-800">
                                {{ ($package['fixed_budget'] ?? null)
                                    ? __('storefront.home.packages.budget_fixed', ['amount' => $package['fixed_budget']])
                                    : ($package['min_budget'] && $package['max_budget']
                                        ? __('storefront.home.packages.budget_range', ['min' => $package['min_budget'], 'max' => $package['max_budget']])
                                        : __('storefront.home.packages.budget_from', ['amount' => $package['min_budget'] ?? $package['max_budget']])) }}
                            </strong>
                        </div>
                    @endif

                    <a
                        href="{{ route('storefront.wizard', array_filter(['occasion' => $package['occasion_code']])) }}"
                        class="sf-button sf-button--navy mt-7 min-h-12 w-full justify-center"
                        data-package-next-step
                    >
                        {{ __('storefront.package.plan_action') }}
                        <span aria-hidden="true">{{ app()->getLocale() === 'ar' ? '←' : '→' }}</span>
                    </a>
                    <p class="mt-3 text-center text-xs leading-5 text-ink-500">{{ __('storefront.package.plan_hint') }}</p>
                </div>
            </div>
        </div>
    </main>
@endsection

@extends('storefront.layouts.app')

@section('title', __('storefront.join_us.title').' · '.__('storefront.common.site_name'))

@section('content')
    @include('storefront.pages.partials.vendor-join')
@endsection

@section('unused_legacy_join')
    <main class="sf-cms-page bg-[var(--sf-ivory)]">
        <section class="sf-vendor-join-hero bg-secondary-900 text-white">
            <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1fr_.8fr] lg:px-8 lg:py-24">
                <div>
                    <p class="text-sm font-bold uppercase tracking-[0.2em] text-secondary-200">{{ __('storefront.join_us.eyebrow') }}</p>
                    <h1 class="mt-5 max-w-3xl text-4xl font-extrabold leading-tight tracking-[-0.05em] sm:text-6xl">{{ __('storefront.join_us.title') }}</h1>
                    <p class="mt-6 max-w-2xl text-base leading-8 text-white/70">{{ __('storefront.join_us.intro') }}</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('filament.vendor.auth.register', ['lang' => app()->getLocale()]) }}" class="sf-button sf-button--light">{{ __('storefront.join_us.primary_cta') }} <span aria-hidden="true">↗</span></a>
                        <a href="{{ route('storefront.home') }}" class="sf-button sf-button--quiet">{{ __('storefront.join_us.secondary_cta') }}</a>
                    </div>
                    <p class="mt-5 max-w-xl text-xs leading-6 text-white/55">{{ __('storefront.join_us.trust_note') }}</p>
                </div>
                <div class="sf-vendor-join-page-art" aria-hidden="true"><img src="{{ asset('images/home-hero/party-hat.png') }}" alt="" width="520" height="520"></div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
            <div class="max-w-2xl"><p class="sf-section-eyebrow">{{ __('storefront.join_us.steps_eyebrow') }}</p><h2 class="mt-3 text-3xl font-extrabold tracking-[-0.04em] text-ink-900 sm:text-5xl">{{ __('storefront.join_us.steps_title') }}</h2></div>
            <ol class="mt-10 grid gap-4 md:grid-cols-4">
                @foreach (['register', 'review', 'verify', 'approval'] as $step)
                    @php($stepCopy = __('storefront.join_us.steps')[$step])
                    <li class="rounded-[1.35rem] border border-ink-200 bg-white p-6 shadow-surface"><span class="flex size-10 items-center justify-center rounded-full bg-secondary-900 text-sm font-extrabold text-white">{{ $loop->iteration }}</span><h3 class="mt-6 text-lg font-extrabold text-ink-900">{{ $stepCopy['title'] }}</h3><p class="mt-3 text-sm leading-7 text-ink-500">{{ $stepCopy['body'] }}</p></li>
                @endforeach
            </ol>
        </section>
    </main>
@endsection

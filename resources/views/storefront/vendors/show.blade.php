@extends('storefront.layouts.app')

@section('title', app(App\Modules\Shared\Application\Services\StorefrontText::class)->translation($vendor, 'business_name').' · '.__('storefront.common.site_name'))

@section('content')
    @php
        $locale = app()->getLocale();
        $storefrontText = app(App\Modules\Shared\Application\Services\StorefrontText::class);
        $vendorName = $storefrontText->translation($vendor, 'business_name') ?: __('storefront.vendor.no_services');
        $city = $vendor->primaryCity ? $storefrontText->translation($vendor->primaryCity, 'name') : '';
        $governorate = $vendor->primaryGovernorate ? $storefrontText->translation($vendor->primaryGovernorate, 'name') : '';
        $vendorBio = $storefrontText->translation($vendor, 'bio');
        $vendorInitial = mb_substr((string) $vendorName, 0, 1);
    @endphp
    <div class="sf-vendor-page bg-[var(--sf-ivory)]">
        <section class="relative overflow-hidden bg-secondary-900 text-white">
            @if ($coverImageUrl)<img src="{{ $coverImageUrl }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-25" onerror="this.hidden=true">@endif
            <div class="absolute inset-0 bg-[linear-gradient(120deg,rgba(16,35,61,.96),rgba(16,35,61,.8))]"></div>
            <div class="relative mx-auto max-w-7xl px-4 py-9 sm:px-6 lg:px-8">
                <a href="{{ url('/'.$locale.'/vendors') }}" class="inline-flex items-center gap-2 text-sm font-bold text-white/70 hover:text-white"><span aria-hidden="true">{{ $locale === 'ar' ? '→' : '←' }}</span>{{ __('storefront.home.vendors_strip.view_all') }}</a>
                <div class="mt-6 flex flex-col gap-5 sm:flex-row sm:items-center">
                    <div class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-full border-4 border-white/20 bg-white/10 text-2xl font-extrabold text-secondary-100">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="" class="h-full w-full object-cover" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                            <span hidden>{{ $vendorInitial }}</span>
                        @else
                            {{ $vendorInitial }}
                        @endif
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <p class="text-sm font-bold uppercase tracking-[0.2em] text-secondary-200">{{ __('storefront.vendor.verified_badge') }}</p>
                            <x-storefront.vendor-rating :vendor="$vendor" class="text-accent-300" />
                        </div>
                        <h1 class="mt-2 text-3xl font-extrabold sm:text-4xl">{{ $vendorName }}</h1>
                        @if ($city || $governorate)<p class="mt-2 text-sm text-white/80">{{ collect([$city, $governorate])->filter()->join(' · ') }}</p>@endif
                    </div>
                </div>
            </div>
        </section>

        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <div>
                <div class="flex flex-wrap gap-3">
                    <div class="min-w-32 rounded-2xl border border-ink-200 bg-white px-5 py-4"><p class="text-xs font-bold uppercase tracking-[0.12em] text-ink-400">{{ __('storefront.vendor.services_count_label') }}</p><p class="mt-2 text-2xl font-extrabold text-ink-900">{{ $vendor->services_count }}</p></div>
                    @if ($city)<div class="min-w-32 rounded-2xl border border-ink-200 bg-white px-5 py-4"><p class="text-xs font-bold uppercase tracking-[0.12em] text-ink-500">{{ __('storefront.search.filter_location') }}</p><p class="mt-2 text-base font-extrabold text-ink-900">{{ $city }}</p></div>@endif
                    @if ($vendor->approvedTypes->isNotEmpty())<div class="min-w-32 rounded-2xl border border-ink-200 bg-white px-5 py-4"><p class="text-xs font-bold uppercase tracking-[0.12em] text-ink-500">{{ __('storefront.vendor.product_types_label') }}</p><p class="mt-2 text-base font-extrabold text-ink-900">{{ $vendor->approvedTypes->count() }}</p></div>@endif
                </div>

                <section id="vendor-services" class="mt-10" aria-labelledby="vendor-services-heading">
                    <div class="flex items-end justify-between gap-4 border-b border-ink-200 pb-5">
                        <div><p class="text-sm font-semibold text-ink-500">{{ __('storefront.discovery.services_heading') }}</p><h2 id="vendor-services-heading" class="mt-1 text-2xl font-extrabold tracking-[-0.03em] text-ink-900">{{ __('storefront.vendor.services_heading') }}</h2></div>
                        @if ($vendor->services_count > 12)<a href="{{ url('/'.$locale.'/search?vendor='.$vendor->public_id) }}" class="text-sm font-bold text-secondary-700 underline underline-offset-4">{{ __('storefront.vendor.view_all_services') }}</a>@endif
                    </div>
                    @if ($services->isEmpty())
                        <div class="mt-6 rounded-[1.5rem] border border-dashed border-ink-300 bg-white px-6 py-14 text-center text-sm text-ink-600">{{ __('storefront.vendor.no_services') }}</div>
                    @else
                        <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($services as $service)
                                <x-storefront.service-card :service="$service" />
                            @endforeach
                        </div>
                    @endif
                </section>
                @if ($vendorBio)
                    <section class="mt-12 max-w-3xl" aria-labelledby="vendor-about-heading">
                        <h2 id="vendor-about-heading" class="text-2xl font-extrabold text-ink-900">{{ __('storefront.vendor.about_heading') }}</h2>
                        <p class="mt-4 whitespace-pre-line text-base leading-8 text-ink-700">{{ $vendorBio }}</p>
                    </section>
                @endif
            </div>
        </div>
    </div>
@endsection

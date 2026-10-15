@extends('storefront.layouts.app')

@section('title', app(App\Modules\Shared\Application\Services\StorefrontText::class)->translation($vendor, 'business_name').' · '.__('storefront.common.site_name'))

@section('content')
    @php
        $locale = app()->getLocale();
        $storefrontText = app(App\Modules\Shared\Application\Services\StorefrontText::class);
        $vendorName = $storefrontText->translation($vendor, 'business_name') ?: __('storefront.vendor.no_services');
        $governorate = $storefrontText->vendorGovernorates($vendor)->pluck('name')->join(' · ');
        $vendorBio = $storefrontText->translation($vendor, 'bio');
        $vendorInitial = mb_substr((string) $vendorName, 0, 1);
    @endphp
    <div class="sf-vendor-page sf-vendor-profile bg-[var(--sf-ivory)]">
        <section class="sf-vendor-profile__hero relative overflow-hidden bg-secondary-900 text-white">
            @if ($coverImageUrl)<img src="{{ $coverImageUrl }}" alt="" class="sf-vendor-profile__cover" onerror="this.hidden=true">@endif
            <div class="sf-vendor-profile__shade absolute inset-0"></div>
            <div class="sf-vendor-identity relative mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <a href="{{ url('/'.$locale.'/vendors') }}" class="inline-flex items-center gap-2 text-sm font-bold text-white/70 hover:text-white"><span aria-hidden="true">{{ $locale === 'ar' ? '→' : '←' }}</span>{{ __('storefront.home.vendors_strip.view_all') }}</a>
                <div class="sf-vendor-profile__identity-row mt-4 flex items-center gap-4">
                    <div class="sf-vendor-profile__avatar flex size-16 shrink-0 items-center justify-center rounded-full border-4 border-white/20 bg-white/10 text-2xl font-extrabold text-secondary-100">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="" class="h-full w-full object-cover" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                            <span hidden>{{ $vendorInitial }}</span>
                        @else
                            {{ $vendorInitial }}
                        @endif
                        <span class="sf-vendor-profile__verified" aria-hidden="true">✓</span>
                    </div>
                    <div class="sf-vendor-profile__name min-w-0">
                        <div>
                            <p class="text-sm font-bold uppercase tracking-[0.2em] text-secondary-200">{{ __('storefront.vendor.verified_badge') }}</p>
                            <x-storefront.vendor-public-reviews :summary="$reviewSummary" class="text-accent-300" />
                        </div>
                        <h1 class="mt-2 text-2xl font-extrabold sm:text-3xl [overflow-wrap:anywhere]">{{ $vendorName }}</h1>
                        @if ($governorate)<p class="sf-vendor-profile__location mt-2 text-sm text-white/80"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12Z"/><circle cx="12" cy="9" r="2"/></svg>{{ $governorate }}</p>@endif
                        @if ($serviceNames->isNotEmpty())
                            <ul class="sf-vendor-profile__specialties" aria-label="{{ __('storefront.vendor.services_heading') }}">
                                @foreach ($serviceNames->take(4) as $serviceName)<li>{{ $serviceName }}</li>@endforeach
                            </ul>
                        @endif
                    </div>
                    <x-storefront.vendor-public-reviews :summary="$reviewSummary" class="sf-vendor-profile__reviews text-accent-300" />
                </div>
            </div>
        </section>

        <div class="sf-vendor-profile__content mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            @if ($galleryItems->isNotEmpty())
                <section data-vendor-portfolio data-vendor-gallery data-service-gallery class="sf-vendor-gallery mb-6 max-w-3xl" aria-labelledby="vendor-gallery-heading">
                    <div class="sf-vendor-gallery__heading">
                        <h2 id="vendor-gallery-heading" class="text-lg font-extrabold text-ink-900">{{ __('storefront.vendor.portfolio_heading') }}</h2>
                        @if ($galleryItems->count() > 1)
                            <div class="sf-vendor-gallery__controls">
                                <button type="button" data-gallery-step="-1" aria-label="{{ __('storefront.common.previous') }}"><span aria-hidden="true">{{ $locale === 'ar' ? '→' : '←' }}</span></button>
                                <button type="button" data-gallery-step="1" aria-label="{{ __('storefront.common.next') }}"><span aria-hidden="true">{{ $locale === 'ar' ? '←' : '→' }}</span></button>
                            </div>
                        @endif
                    </div>
                    <div class="sf-vendor-gallery__frame overflow-hidden rounded-[1.5rem] bg-secondary-50">
                        <img data-service-gallery-main src="{{ $galleryItems->first()['url'] }}" alt="{{ $vendorName }}" class="sf-vendor-gallery__image aspect-[16/9] w-full object-cover" width="1200" height="675">
                        <span class="sf-vendor-gallery__position" data-gallery-position aria-live="polite" dir="ltr">1 / {{ $galleryItems->count() }}</span>
                    </div>
                    @if ($galleryItems->count() > 1)
                        <div class="sf-vendor-gallery__thumbnails mt-4 flex items-center gap-3">
                            <div class="flex min-w-0 flex-1 gap-3 overflow-x-auto" aria-label="{{ __('storefront.vendor.gallery_heading') }}">
                                @foreach ($galleryItems as $item)
                                    <button type="button" data-service-gallery-image="{{ $item['url'] }}" aria-label="{{ $vendorName }} · {{ $loop->iteration }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" class="size-16 shrink-0 overflow-hidden rounded-xl border-2 border-transparent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary-600 aria-pressed:border-secondary-600">
                                        <img src="{{ $item['thumb'] }}" alt="" width="64" height="64" class="h-full w-full object-cover" loading="lazy">
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>
            @endif
            <section data-vendor-gallery-empty @if ($galleryItems->isNotEmpty()) hidden @endif class="mb-6 text-sm text-ink-600">{{ __('storefront.vendor.gallery_empty') }}</section>
            <div>
                <section id="vendor-services" class="sf-vendor-services" aria-labelledby="vendor-services-heading">
                    <h2 id="vendor-services-heading" class="text-2xl font-extrabold tracking-[-0.03em] text-ink-900">{{ __('storefront.vendor.services_heading') }}</h2>
                    <div data-vendor-service-names class="sf-vendor-service-names">
                        @if ($serviceNames->isNotEmpty())
                            <p>{{ $serviceNames->take(3)->join(' · ') }}</p>
                        @endif
                    </div>
                    <form data-vendor-text-search class="sf-vendor-search" method="GET" action="{{ route('storefront.vendors.show', ['publicId' => $vendor->slug ?: $vendor->public_id]) }}#vendor-services" role="search">
                        <label class="sr-only" for="vendor-service-search">{{ __('storefront.vendor.search_services') }}</label>
                        @if ($selectedType)<input type="hidden" name="type" value="{{ $selectedType->value }}">@endif
                        <input id="vendor-service-search" type="search" name="q" value="{{ $searchQuery }}" placeholder="{{ __('storefront.vendor.search_services') }}" maxlength="255">
                        <button type="submit" class="sf-button sf-button--navy">{{ __('storefront.common.search') }}</button>
                        @if ($searchQuery !== '')<a class="sf-vendor-search__clear" href="{{ route('storefront.vendors.show', ['publicId' => $vendor->slug ?: $vendor->public_id]) }}#vendor-services">{{ __('storefront.search.clear_filters') }}</a>@endif
                    </form>
                    <nav data-vendor-service-filter class="sf-vendor-type-nav" aria-label="{{ __('storefront.search.filter_type') }}">
                        <ul>
                            <li><a data-vendor-filter-type="" href="{{ route('storefront.vendors.show', ['publicId' => $vendor->slug ?: $vendor->public_id, 'q' => $searchQuery ?: null]) }}#vendor-services" @if ($selectedType === null) aria-current="page" @endif>{{ __('storefront.search.filter_all') }}</a></li>
                            @foreach (\App\Modules\Catalog\Domain\Enums\ProductType::cases() as $productType)
                                @if ($serviceTypeCounts->has($productType->value))
                                    <li data-vendor-service-type="{{ $productType->value }}"><a data-vendor-filter-type="{{ $productType->value }}" href="{{ route('storefront.vendors.show', ['publicId' => $vendor->slug ?: $vendor->public_id, 'type' => $productType->value, 'q' => $searchQuery ?: null]) }}#vendor-services" @if ($selectedType === $productType) aria-current="page" @endif>{{ $productType->getLabel() }}</a></li>
                                @endif
                            @endforeach
                        </ul>
                    </nav>
                    @if ($services->isEmpty())
                        <div class="mt-6 rounded-[1.5rem] border border-dashed border-ink-300 bg-white px-6 py-14 text-center text-sm text-ink-600">{{ $searchQuery !== '' || $selectedType ? __('storefront.search.empty_title') : __('storefront.vendor.no_services') }}</div>
                    @else
                        <div data-vendor-services-grid class="sf-vendor-services-grid mt-6 grid gap-4">
                            @foreach ($services as $service)
                                <x-storefront.service-card :service="$service" :show-vendor="false" />
                            @endforeach
                        </div>
                    @endif
                    @if ($services->hasPages())<div class="mt-6">{{ $services->fragment('vendor-services')->links() }}</div>@endif
                </section>
                @if ($vendorBio)
                    <section class="sf-vendor-profile__about" aria-labelledby="vendor-about-heading">
                        <div class="sf-vendor-profile__bio">
                            <h2 id="vendor-about-heading" class="text-2xl font-extrabold text-ink-900">{{ __('storefront.vendor.about_heading') }}</h2>
                            <p class="mt-4 whitespace-pre-line text-base leading-8 text-ink-700">{{ $vendorBio }}</p>
                        </div>
                        <dl class="sf-vendor-profile__facts">
                            @if ($governorate)<div><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12Z"/><circle cx="12" cy="9" r="2"/></svg></span><dt>{{ __('storefront.search.filter_location') }}</dt><dd>{{ $governorate }}</dd></div>@endif
                            <div><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="6" width="16" height="15" rx="2"/><path d="M8 3v6m8-6v6M4 11h16m-11 4h6m-6 3h4"/></svg></span><dt>{{ __('storefront.vendor.services_count_label') }}</dt><dd>{{ trans_choice('storefront.vendor.services_count', (int) $vendor->services_count, ['count' => $vendor->services_count]) }}</dd></div>
                            @if ($serviceTypeCounts->isNotEmpty())
                                <div><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="7" width="16" height="14" rx="2"/><path d="M8 7V3h8v4M4 12h16m-9-2v4h2v-4"/></svg></span><dt>{{ __('storefront.vendor.product_types_label') }}</dt><dd>{{ $serviceTypeCounts->keys()->map(fn ($type) => \App\Modules\Catalog\Domain\Enums\ProductType::from($type)->getLabel())->join(' · ') }}</dd></div>
                            @endif
                            <div><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/></svg></span><dt>{{ __('storefront.vendor.verified_badge') }}</dt><dd>{{ __('storefront.vendor.member_since', ['date' => $vendor->created_at->translatedFormat('M Y')]) }}</dd></div>
                        </dl>
                    </section>
                @endif
            </div>
        </div>
    </div>
@endsection

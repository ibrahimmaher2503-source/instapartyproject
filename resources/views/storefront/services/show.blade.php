@extends('storefront.layouts.app')

@php
    use App\Modules\Catalog\Domain\Enums\ProductType;

    $locale = app()->getLocale();
    $storefrontText = app(App\Modules\Shared\Application\Services\StorefrontText::class);
    $serviceName = $storefrontText->translation($service, 'name') ?: __('storefront.discovery.services_heading');
    $serviceDescription = $storefrontText->translation($service, 'long_description');
    $shortDescription = $storefrontText->translation($service, 'short_description');
    $vendor = $service->vendor;
    $vendorName = $vendor ? $storefrontText->translation($vendor, 'business_name') : '';
    $categoryName = $service->category ? $storefrontText->translation($service->category, 'name') : '';
    $serviceAreaHint = $vendor?->primaryCity ? $storefrontText->translation($vendor->primaryCity, 'name') : '';
    $serviceAreaHint = $serviceAreaHint ?: ($vendor?->primaryGovernorate ? $storefrontText->translation($vendor->primaryGovernorate, 'name') : '');
    $ratingCount = (int) ($service->rating_count ?? 0);
    $isOutOfStock = $service->product_type === ProductType::Sale
        && $service->saleDetail?->stock_quantity !== null
        && $service->saleDetail->stock_quantity <= 0;
    $gallery = $service->getMedia('gallery');
    $fallbackPath = app(App\Modules\Shared\Application\Services\StorefrontImageFallback::class)->service(
        (string) $service->public_id,
        $service->product_type->value,
        [(string) ($service->category?->code ?? ''), $categoryName, $serviceName],
    );
    $fallbackImage = asset($fallbackPath);
    $primaryImage = $gallery->isEmpty() ? $fallbackImage : $gallery->first()?->getUrl('large');
@endphp

@section('title', $serviceName.' · '.__('storefront.common.site_name'))

@section('content')
    <div class="sf-service-page bg-[var(--sf-ivory)]">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            <nav class="sf-service-breadcrumb" aria-label="{{ __('storefront.common.breadcrumb') }}">
                <a href="{{ route('storefront.home') }}">{{ __('storefront.nav.home') }}</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('storefront.search') }}">{{ __('storefront.nav.search') }}</a>
                @if ($service->category)
                    <span aria-hidden="true">/</span>
                    <a href="{{ route('storefront.categories.show', ['identifier' => $service->category->code]) }}">{{ $categoryName }}</a>
                @endif
                <span aria-hidden="true">/</span>
                <span aria-current="page">{{ $serviceName }}</span>
            </nav>

            <div class="mt-7 grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(19rem,1fr)] lg:gap-x-8 lg:gap-y-0">
                <div class="min-w-0 lg:col-start-1 lg:row-start-1">
                    <div data-service-gallery class="grid gap-3 {{ $gallery->count() > 1 ? 'sm:grid-cols-[minmax(0,1fr)_7rem]' : '' }}">
                        <div class="overflow-hidden rounded-[1.6rem] bg-secondary-50">
                            <img data-service-gallery-main src="{{ $primaryImage }}" alt="{{ $serviceName }}" class="aspect-[1.3] h-full w-full object-cover" width="1200" height="920" fetchpriority="high" onerror="this.onerror=null;this.src='{{ $fallbackImage }}';">
                        </div>
                        @if ($gallery->count() > 1)
                            <div class="flex gap-3 overflow-x-auto sm:flex-col" aria-label="{{ __('storefront.service.gallery_heading') }}">
                                @foreach ($gallery as $media)
                                    <button type="button" data-service-gallery-image="{{ $media->getUrl('large') }}" aria-label="{{ $serviceName }} · {{ $loop->iteration }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" class="w-20 shrink-0 overflow-hidden rounded-2xl border-2 border-transparent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary-600 aria-pressed:border-secondary-600 sm:w-full">
                                        <img src="{{ $media->getUrl('thumb') }}" alt="" width="200" height="200" class="aspect-square w-full object-cover" loading="lazy" onerror="this.closest('button').hidden=true;">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="mt-7 flex flex-wrap items-center gap-3">
                        <x-storefront.service-type-badge :type="$service->product_type" />
                        @if ($categoryName)
                            <span class="text-sm font-semibold text-ink-500">{{ $categoryName }}</span>
                        @endif
                        <x-storefront.rating-display :average="$service->rating_avg" :count="$ratingCount" class="text-accent-600" />
                    </div>
                    <h1 class="mt-4 max-w-3xl text-4xl font-extrabold leading-tight tracking-[-0.045em] text-ink-900 sm:text-[2.75rem]">{{ $serviceName }}</h1>
                    @if ($shortDescription)
                        <p class="mt-4 max-w-2xl text-lg leading-8 text-ink-600">{{ $shortDescription }}</p>
                    @endif
                </div>

                <aside class="lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:sticky lg:top-24">
                    <div class="rounded-[1.6rem] bg-secondary-900 p-6 text-white shadow-deep sm:p-7">
                        <div class="flex items-end justify-between gap-4 border-b border-white/15 pb-5">
                            <div>
                                <p class="text-sm text-white/60">{{ __('storefront.service.from') }}</p>
                                <p class="mt-1 text-3xl font-extrabold tracking-[-0.04em]"><x-storefront.price :minor="$service->base_price_minor" :currency="$service->base_price_currency" /></p>
                            </div>
                            <x-storefront.service-type-badge :type="$service->product_type" />
                        </div>

                        <dl class="mt-5 space-y-4 text-sm">
                            @if ($serviceAreaHint)
                                <div class="flex items-center justify-between gap-4"><dt class="text-white/60">{{ __('storefront.search.filter_location') }}</dt><dd class="font-semibold">{{ $serviceAreaHint }}</dd></div>
                            @endif
                            @if ($service->product_type === ProductType::Rental && $service->rentalDetail?->setup_time_minutes)
                                <div class="flex items-center justify-between gap-4"><dt class="text-white/60">{{ __('storefront.service.setup_time') }}</dt><dd class="font-semibold"><bdi dir="ltr">{{ $service->rentalDetail->setup_time_minutes }}</bdi> {{ __('storefront.common.minutes') }}</dd></div>
                            @elseif ($service->product_type === ProductType::Sale && $service->saleDetail?->lead_time_hours)
                                <div class="flex items-center justify-between gap-4"><dt class="text-white/60">{{ __('storefront.service.lead_time') }}</dt><dd class="font-semibold"><bdi dir="ltr">{{ $service->saleDetail->lead_time_hours }}</bdi> {{ __('storefront.common.hours') }}</dd></div>
                            @elseif ($service->product_type === ProductType::Digital && $service->digitalDetail?->delivery_method)
                                <div class="flex items-center justify-between gap-4"><dt class="text-white/60">{{ __('storefront.service.delivery_method') }}</dt><dd class="font-semibold">{{ __('catalog.delivery_methods.'.(string) $service->digitalDetail->delivery_method) }}</dd></div>
                            @endif
                        </dl>

                        @if ($isOutOfStock)
                            <button type="button" class="sf-button mt-7 min-h-12 w-full cursor-not-allowed justify-center bg-white/10 text-white/55" disabled>{{ __('storefront.service.out_of_stock') }}</button>
                        @else
                            <a href="{{ url('/'.$locale.'/wizard?service='.$service->public_id) }}" class="sf-button sf-button--light mt-7 min-h-12 w-full justify-center" aria-describedby="service-cart-hint">
                                {{ __('storefront.service.add_to_cart') }}
                                <span aria-hidden="true">{{ $locale === 'ar' ? '←' : '→' }}</span>
                            </a>
                        @endif
                        @unless ($isOutOfStock)
                            <p id="service-cart-hint" class="mt-3 text-center text-xs leading-5 text-white/60">{{ __('storefront.service.add_to_cart_hint') }}</p>
                        @endunless
                    </div>

                    @if ($vendor)
                        <a href="{{ url('/'.$locale.'/vendors/'.$vendor->public_id) }}" class="sf-service-vendor mt-5 flex items-center gap-4 rounded-[1.35rem] border border-ink-200 bg-white p-5 transition hover:border-secondary-300 hover:shadow-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary-600">
                            @if ($vendor->logo_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($vendor->logo_path) }}" alt="" width="52" height="52" class="size-13 rounded-full object-cover" loading="lazy" onerror="this.onerror=null;this.hidden=true;this.nextElementSibling.hidden=false;">
                                <span hidden class="flex size-13 items-center justify-center rounded-full bg-secondary-50 text-lg font-extrabold text-secondary-700">{{ mb_substr((string) $vendorName, 0, 1) }}</span>
                            @else
                                <span class="flex size-13 items-center justify-center rounded-full bg-secondary-50 text-lg font-extrabold text-secondary-700">{{ mb_substr((string) $vendorName, 0, 1) }}</span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-bold text-ink-400">{{ $vendor->approval_status instanceof \App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState ? __('storefront.service.verified_vendor') : __('storefront.service.view_profile') }}</span>
                                <span class="mt-1 block truncate font-bold text-ink-900">{{ $vendorName }}</span>
                                @if ($serviceAreaHint)
                                    <span class="mt-1 block text-xs text-ink-500">{{ $serviceAreaHint }}</span>
                                @endif
                                <x-storefront.vendor-rating :vendor="$vendor" class="mt-2 text-accent-600" />
                            </span>
                            <span class="text-xl text-secondary-700" aria-hidden="true">{{ $locale === 'ar' ? '←' : '→' }}</span>
                        </a>
                    @endif
                </aside>

                <div class="min-w-0 lg:col-start-1 lg:row-start-2">
                    <x-storefront.service-attributes :service="$service" :location="$serviceAreaHint" class="mt-10 border-t border-ink-200 pt-8" />

                    @if ($serviceDescription)
                        <section class="mt-10 border-t border-ink-200 pt-8" aria-labelledby="service-about-heading">
                            <h2 id="service-about-heading" class="text-2xl font-extrabold tracking-[-0.03em] text-ink-900">{{ __('storefront.service.about_heading') }}</h2>
                            <div class="prose prose-ink mt-5 max-w-3xl text-base leading-8 text-ink-600">
                                {!! nl2br(e($serviceDescription)) !!}
                            </div>
                        </section>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

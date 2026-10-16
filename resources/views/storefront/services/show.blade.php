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
    $mediaUrls = app(App\Modules\Shared\Application\Services\PublicMediaUrl::class);
    $vendorLogo = $mediaUrls->publicPath($vendor?->logo_path);
    $categoryName = $service->category ? $storefrontText->translation($service->category, 'name') : '';
    $ratingCount = (int) ($service->rating_count ?? 0);
    $isOutOfStock = $service->product_type === ProductType::Sale
        && $service->saleDetail?->stock_quantity !== null
        && $service->saleDetail->stock_quantity <= 0;
    $gallery = $service->getMedia('gallery')->map(fn ($media): array => [
        'url' => $mediaUrls->for($media, 'large'),
        'thumb' => $mediaUrls->for($media, 'thumb'),
    ])->filter(fn (array $item): bool => $item['url'] !== null && $item['thumb'] !== null)->values();
    $fallbackPath = app(App\Modules\Shared\Application\Services\StorefrontImageFallback::class)->service(
        (string) $service->public_id,
        $service->product_type->value,
        [(string) ($service->category?->code ?? ''), $categoryName, $serviceName],
    );
    $fallbackImage = asset($fallbackPath);
    $primaryImage = $gallery->isEmpty() ? $fallbackImage : $gallery->first()['url'];
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
                                @foreach ($gallery as $image)
                                    <button type="button" data-service-gallery-image="{{ $image['url'] }}" aria-label="{{ $serviceName }} · {{ $loop->iteration }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" class="w-20 shrink-0 overflow-hidden rounded-2xl border-2 border-transparent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary-600 aria-pressed:border-secondary-600 sm:w-full">
                                        <img src="{{ $image['thumb'] }}" alt="" width="200" height="200" class="aspect-square w-full object-cover" loading="lazy" onerror="this.closest('button').hidden=true;">
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
                    @if ($service->themes->isNotEmpty())
                        <section class="mt-5" aria-labelledby="service-themes-heading" data-service-themes>
                            <h2 id="service-themes-heading" class="text-sm font-bold text-ink-700">{{ __('catalog.service_themes') }}</h2>
                            <ul class="mt-2 flex flex-wrap gap-2">
                                @foreach ($service->themes->where('is_active', true) as $theme)
                                    <li class="rounded-full border border-secondary-200 bg-white px-3 py-1 text-sm font-semibold text-secondary-800">
                                        {{ $theme->getTranslation('name', $locale, useFallbackLocale: true) }}
                                    </li>
                                @endforeach
                            </ul>
                        </section>
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

                        @if (($service->product_type === ProductType::Rental && $service->rentalDetail?->setup_time_minutes)
                            || ($service->product_type === ProductType::Sale && $service->saleDetail?->lead_time_hours)
                            || ($service->product_type === ProductType::Digital && $service->digitalDetail?->delivery_method))
                        <dl class="mt-5 space-y-4 text-sm">
                            @if ($service->product_type === ProductType::Rental && $service->rentalDetail?->setup_time_minutes)
                                <div class="flex items-center justify-between gap-4"><dt class="text-white/60">{{ __('storefront.service.setup_time') }}</dt><dd class="font-semibold"><bdi dir="ltr">{{ $service->rentalDetail->setup_time_minutes }}</bdi> {{ __('storefront.common.minutes') }}</dd></div>
                            @elseif ($service->product_type === ProductType::Sale && $service->saleDetail?->lead_time_hours)
                                <div class="flex items-center justify-between gap-4"><dt class="text-white/60">{{ __('storefront.service.lead_time') }}</dt><dd class="font-semibold"><bdi dir="ltr">{{ $service->saleDetail->lead_time_hours }}</bdi> {{ __('storefront.common.hours') }}</dd></div>
                            @elseif ($service->product_type === ProductType::Digital && $service->digitalDetail?->delivery_method)
                                <div class="flex items-center justify-between gap-4"><dt class="text-white/60">{{ __('storefront.service.delivery_method') }}</dt><dd class="font-semibold">{{ __('catalog.delivery_methods.'.(string) $service->digitalDetail->delivery_method) }}</dd></div>
                            @endif
                        </dl>
                        @endif

                        @if ($isOutOfStock)
                            <button type="button" class="sf-button mt-7 min-h-12 w-full cursor-not-allowed justify-center bg-white/10 text-white/55" disabled>{{ __('storefront.service.out_of_stock') }}</button>
                        @else
                            <a href="{{ $wizardUrl }}" data-service-wizard-link class="sf-button sf-button--light mt-7 min-h-12 w-full justify-center" aria-describedby="service-cart-hint" aria-disabled="false">
                                {{ __('storefront.service.add_to_cart') }}
                                <span aria-hidden="true">{{ $locale === 'ar' ? '←' : '→' }}</span>
                            </a>
                        @endif
                        @unless ($isOutOfStock)
                            <p id="service-cart-hint" class="mt-3 text-center text-xs leading-5 text-white/60">{{ __('storefront.service.add_to_cart_hint') }}</p>
                        @endunless
                        <a href="{{ route('storefront.inquiries.create', ['locale' => $locale, 'servicePublicId' => $service->public_id]) }}" class="mt-5 block rounded-xl border border-white/25 px-4 py-3 text-center font-bold text-white transition hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                            {{ __('communication::communication.vendor_inquiries.ask_vendor') }}
                        </a>
                        <p class="mt-2 text-center text-xs leading-5 text-white/60">{{ __('communication::communication.vendor_inquiries.ask_vendor_hint') }}</p>
                    </div>

                    <section class="mt-5 rounded-[1.35rem] border border-ink-200 bg-white p-5" aria-labelledby="service-estimate-title">
                        <h2 id="service-estimate-title" class="text-lg font-extrabold text-ink-900">{{ __('storefront.service.estimate_title') }}</h2>
                        <p class="mt-2 text-sm leading-6 text-ink-600">{{ __('storefront.service.estimate_note') }}</p>
                        @if ($service->product_type === ProductType::Rental)
                            <p class="mt-2 text-sm leading-6 text-ink-600">{{ __('storefront.service.rental_estimate_note') }}</p>
                        @endif

                        <p class="mt-4 text-sm font-semibold text-ink-500">{{ __('storefront.service.estimate_amount') }}</p>
                        <bdi data-service-estimate-total dir="ltr" class="mt-1 block text-2xl font-extrabold text-ink-900"><x-storefront.price :minor="$estimate->getMinorAmount()->toInt()" :currency="$service->base_price_currency" /></bdi>

                        <form
                            method="GET"
                            action="{{ route('storefront.services.show', ['locale' => $locale, 'servicePublicId' => $service->public_id]) }}"
                            data-service-estimate
                            data-estimate-quantity="{{ $quantity }}"
                            data-estimate-refresh-message="{{ __('storefront.service.estimate_refresh_required') }}"
                            data-availability-endpoint="{{ url('/api/v1/customer/services/'.$service->public_id.'/check-availability') }}"
                            data-product-type="{{ $service->product_type->value }}"
                            data-availability-error="{{ __('storefront.service.availability_error') }}"
                            data-availability-date-required="{{ __('storefront.service.availability_date_required') }}"
                            data-availability-date-invalid="{{ __('storefront.service.availability_date_invalid') }}"
                            data-availability-duration-invalid="{{ __('storefront.service.availability_duration_invalid') }}"
                            data-availability-quantity-invalid="{{ __('storefront.service.availability_quantity_invalid') }}"
                            data-availability-checking="{{ __('storefront.service.availability_checking') }}"
                            data-availability-available-rental="{{ __('storefront.service.availability_available_rental') }}"
                            data-availability-available-sale="{{ __('storefront.service.availability_available_sale') }}"
                            data-availability-available-digital="{{ __('storefront.service.availability_available_digital') }}"
                            data-availability-unavailable="{{ __('storefront.service.availability_unavailable') }}"
                            class="mt-5 space-y-4"
                        >
                            <input type="hidden" name="timezone" value="{{ $selection['timezone'] }}" data-browser-timezone>
                            <label class="block text-sm font-bold text-ink-800">
                                <span>{{ __('storefront.service.date') }}</span>
                                <input type="datetime-local" name="event_starts_at" value="{{ $selection['event_starts_at'] }}" class="sf-field mt-2 w-full" data-service-estimate-start>
                            </label>
                            @if ($service->product_type === ProductType::Rental)
                                <input type="hidden" name="event_ends_at" value="{{ $selection['event_ends_at'] }}" data-service-estimate-end>
                                <label class="block text-sm font-bold text-ink-800">
                                    <span>{{ __('storefront.service.rental_duration') }} ({{ __('storefront.common.hours') }})</span>
                                    <input type="number" name="rental_duration_hours" min="1" max="720" value="{{ $durationHours }}" class="sf-field mt-2 w-full" data-service-estimate-duration>
                                </label>
                                <input type="hidden" name="quantity" value="1" data-service-estimate-quantity>
                            @else
                                <label class="block text-sm font-bold text-ink-800">
                                    <span>{{ __('storefront.service.quantity') }}</span>
                                    <input type="number" name="quantity" min="1" max="100" value="{{ $selection['quantity'] }}" class="sf-field mt-2 w-full" data-service-estimate-quantity>
                                </label>
                            @endif
                            <div class="flex flex-col gap-3 sm:flex-row">
                                <button type="submit" class="sf-button sf-button--secondary min-h-11 flex-1 justify-center">{{ __('storefront.service.update_estimate') }}</button>
                                <button type="button" data-service-check-availability class="sf-button min-h-11 flex-1 justify-center border border-secondary-200 bg-white text-secondary-900">{{ __('storefront.service.check_availability') }}</button>
                            </div>
                            <p data-service-availability-status class="min-h-6 text-sm leading-6 text-ink-700" role="status" aria-live="polite"></p>
                            <p data-service-estimate-refresh-status class="min-h-6 text-sm leading-6 text-amber-800" role="status" aria-live="polite" tabindex="-1"></p>
                        </form>
                    </section>

                    @if ($vendor)
                        <a href="{{ route('storefront.vendors.show', ['publicId' => $vendor->slug ?: $vendor->public_id]) }}" class="sf-service-vendor mt-5 flex items-center gap-4 rounded-[1.35rem] border border-ink-200 bg-white p-5 transition hover:border-secondary-300 hover:shadow-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary-600">
                            @if ($vendorLogo)
                                <img src="{{ $vendorLogo }}" alt="" width="52" height="52" class="size-13 rounded-full object-cover" loading="lazy" onerror="this.onerror=null;this.hidden=true;this.nextElementSibling.hidden=false;">
                                <span hidden class="flex size-13 items-center justify-center rounded-full bg-secondary-50 text-lg font-extrabold text-secondary-700">{{ mb_substr((string) $vendorName, 0, 1) }}</span>
                            @else
                                <span class="flex size-13 items-center justify-center rounded-full bg-secondary-50 text-lg font-extrabold text-secondary-700">{{ mb_substr((string) $vendorName, 0, 1) }}</span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-bold text-ink-400">{{ $vendor->approval_status instanceof \App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState ? __('storefront.service.verified_vendor') : __('storefront.service.view_profile') }}</span>
                                <span class="mt-1 block truncate font-bold text-ink-900">{{ $vendorName }}</span>
                                <x-storefront.vendor-rating :vendor="$vendor" class="mt-2 text-accent-600" />
                            </span>
                            <span class="text-xl text-secondary-700" aria-hidden="true">{{ $locale === 'ar' ? '←' : '→' }}</span>
                        </a>
                    @endif
                </aside>

                <div class="min-w-0 lg:col-start-1 lg:row-start-2">
                    <x-storefront.service-attributes :service="$service" class="mt-10 border-t border-ink-200 pt-8" />

                    @if ($service->packages->isNotEmpty())
                        <section class="mt-10 border-t border-ink-200 pt-8" aria-labelledby="service-packages-heading" data-service-packages>
                            <h2 id="service-packages-heading" class="text-2xl font-extrabold tracking-[-0.03em] text-ink-900">{{ __('storefront.wizard.step1.package_label') }}</h2>
                            <p class="mt-2 text-sm leading-6 text-ink-600">{{ __('storefront.wizard.step1.package_hint') }}</p>
                            <ul class="mt-5 grid gap-4 sm:grid-cols-2">
                                @foreach ($service->packages as $package)
                                    <li class="rounded-2xl border border-ink-200 bg-white p-5">
                                        <h3 class="font-bold text-ink-900">{{ $package->getTranslation('name', $locale, useFallbackLocale: true) }}</h3>
                                        <p class="mt-2 text-sm leading-6 text-ink-600">{{ $package->getTranslation('description', $locale, useFallbackLocale: true) }}</p>
                                        <p class="mt-3 font-extrabold text-secondary-800"><x-storefront.price :minor="$package->price_minor" :currency="$package->currency" /></p>
                                        <ul class="mt-3 space-y-1 text-sm text-ink-600">
                                            @foreach ($package->components as $component)
                                                <li>{{ $component->getTranslation('name', $locale, useFallbackLocale: true) }} · {{ __('storefront.wizard.step1.package_component_quantity', ['quantity' => $component->quantity]) }}</li>
                                            @endforeach
                                        </ul>
                                        <a href="{{ route('storefront.wizard', array_filter(['locale' => $locale, 'service' => $service->public_id, 'package_public_id' => $package->public_id, 'event_starts_at' => $selection['event_starts_at'], 'event_ends_at' => $selection['event_ends_at'], 'quantity' => $selection['quantity'], 'timezone' => $selection['timezone'], 'rental_duration_hours' => $durationHours])) }}" class="sf-button sf-button--secondary mt-5 w-full justify-center">{{ __('storefront.service.choose_package') }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @if ($serviceDescription)
                        <section class="mt-10 border-t border-ink-200 pt-8" aria-labelledby="service-about-heading">
                            <h2 id="service-about-heading" class="text-2xl font-extrabold tracking-[-0.03em] text-ink-900">{{ __('storefront.service.about_heading') }}</h2>
                            <div class="prose prose-ink mt-5 max-w-3xl text-base leading-8 text-ink-600">
                                {!! nl2br(e($serviceDescription)) !!}
                            </div>
                        </section>
                    @endif

                    @if (! empty($vendorPortfolioItems))
                        <section data-vendor-portfolio class="mt-10 border-t border-ink-200 pt-8" aria-labelledby="vendor-portfolio-heading">
                            <h2 id="vendor-portfolio-heading" class="text-2xl font-extrabold tracking-[-0.03em] text-ink-900">{{ __('storefront.vendor.portfolio_heading') }}</h2>
                            <ul class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3" aria-label="{{ __('storefront.vendor.portfolio_heading') }}">
                                @foreach ($vendorPortfolioItems as $item)
                                    <li class="overflow-hidden rounded-2xl bg-secondary-50">
                                        <img src="{{ $item['url'] }}" alt="{{ $vendorName }}" class="aspect-square w-full object-cover" width="480" height="480" loading="lazy">
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

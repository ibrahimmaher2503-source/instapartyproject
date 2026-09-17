@props(['service'])

@php
    $storefrontText = app(App\Modules\Shared\Application\Services\StorefrontText::class);
    $serviceName = $storefrontText->translation($service, 'name') ?: __('storefront.discovery.services_heading');
    $categoryName = $service->category ? $storefrontText->translation($service->category, 'name') : '';
    $fallbackPath = app(App\Modules\Shared\Application\Services\StorefrontImageFallback::class)->service(
        (string) $service->public_id,
        $service->product_type->value,
        [(string) ($service->category?->code ?? ''), $categoryName, $serviceName],
    );
    $fallbackImage = asset($fallbackPath);
    $mediaImage = $service->getFirstMediaUrl('gallery', 'medium');
    $image = blank($mediaImage) ? $fallbackImage : $mediaImage;
    $vendorName = $service->vendor ? $storefrontText->translation($service->vendor, 'business_name') : '';
    $locationName = $service->vendor?->primaryCity
        ? $storefrontText->translation($service->vendor->primaryCity, 'name')
        : ($service->vendor?->primaryGovernorate ? $storefrontText->translation($service->vendor->primaryGovernorate, 'name') : '');
    $shortDescription = $storefrontText->translation($service, 'short_description');
    $ratingCount = (int) ($service->rating_count ?? 0);
@endphp

<article class="sf-catalog-card group overflow-hidden rounded-[1.35rem] border border-ink-200/80 bg-white transition duration-200 hover:-translate-y-1 hover:shadow-deep">
    <a href="{{ url('/'.app()->getLocale().'/services/'.$service->public_id) }}" class="block focus:outline-none focus-visible:ring-4 focus-visible:ring-secondary-200 focus-visible:ring-inset">
        <div class="sf-catalog-card__media relative aspect-[4/3] overflow-hidden bg-secondary-50">
            <img src="{{ $image }}" alt="{{ $serviceName }}" width="800" height="600" class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.025]" loading="lazy" onerror="this.onerror=null;this.src='{{ $fallbackImage }}';">
            <span class="absolute inset-inline-start-4 inset-block-start-4">
                <x-storefront.service-type-badge :type="$service->product_type" />
            </span>
        </div>
        <div class="sf-catalog-card__body space-y-3 p-5">
            <div class="flex items-center justify-between gap-3 text-xs font-semibold uppercase tracking-[0.14em] text-ink-400">
                <span class="truncate">{{ $categoryName ?: __('storefront.discovery.services_heading') }}</span>
                @if ($ratingCount > 0 && $service->rating_avg)
                    <span class="shrink-0 text-accent-500">★ {{ __('storefront.discovery.rating_label', ['avg' => number_format((float) $service->rating_avg, 1), 'count' => $ratingCount]) }}</span>
                @endif
            </div>
            <h3 class="line-clamp-2 text-lg font-bold leading-snug text-ink-900">
                {{ $serviceName }}
            </h3>
            @if ($shortDescription)
                <p class="line-clamp-2 text-sm leading-6 text-ink-500">{{ $shortDescription }}</p>
            @endif
            @if ($vendorName || $locationName)
                <div class="sf-catalog-card__meta">
                    @if ($vendorName)
                        <span>{{ $vendorName }}</span>
                    @endif
                    @if ($locationName)
                        <span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z" />
                                <circle cx="12" cy="10" r="2" />
                            </svg>
                            {{ $locationName }}
                        </span>
                    @endif
                </div>
            @endif
            <div class="flex items-end justify-between gap-4 border-t border-ink-100 pt-4">
                <div>
                    <p class="text-xs text-ink-400">{{ __('storefront.service.from') }}</p>
                    <p class="mt-1 text-lg font-extrabold text-secondary-600"><x-storefront.price :minor="$service->base_price_minor" :currency="$service->base_price_currency" /></p>
                </div>
                <span class="sf-catalog-card__arrow" aria-hidden="true">→</span>
            </div>
        </div>
    </a>
</article>

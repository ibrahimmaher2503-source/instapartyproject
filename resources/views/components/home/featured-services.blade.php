@props(['block', 'services' => []])

@php
    $payload = (array) ($block['payload'] ?? []);
    $items = collect($services)->take(4);
    $storefrontText = app(App\Modules\Shared\Application\Services\StorefrontText::class);
 @endphp

@if ($items->isNotEmpty())
    <section class="sf-home-section sf-services-section" aria-labelledby="services-heading-{{ $block['public_id'] }}">
        <div class="sf-home-container">
            <x-home.section-header
                :id="'services-heading-'.$block['public_id']"
                :eyebrow="__('storefront.home.featured.eyebrow')"
                :title="$payload['title'] ?? __('storefront.home.featured.heading')"
                :description="__('storefront.home.featured.sub')"
                :href="url('/'.app()->getLocale().'/search')"
                :link-label="__('storefront.home.featured.view_all')"
            />

            <ul class="sf-service-rail" role="list">
                @foreach ($items as $service)
                    @php
                        $serviceName = $storefrontText->translation($service, 'name') ?: __('storefront.discovery.services_heading');
                        $categoryName = $service->category ? $storefrontText->translation($service->category, 'name') : '';
                        $vendorName = $service->vendor ? $storefrontText->translation($service->vendor, 'business_name') : '';
                        $cityName = $service->vendor?->primaryCity
                            ? $storefrontText->translation($service->vendor->primaryCity, 'name')
                            : '';
                        $fallbackPath = app(App\Modules\Shared\Application\Services\StorefrontImageFallback::class)->service(
                            (string) $service->public_id,
                            $service->product_type->value,
                            [(string) ($service->category?->code ?? ''), $categoryName, $serviceName],
                        );
                        $fallback = asset($fallbackPath);
                        $mediaImage = $service->getFirstMediaUrl('gallery', 'medium');
                        $serviceImage = blank($mediaImage) ? $fallback : $mediaImage;
                        $serviceUrl = url('/'.app()->getLocale().'/services/'.$service->public_id);
                        $price = \Brick\Money\Money::ofMinor((int) $service->base_price_minor, (string) $service->base_price_currency)->formatTo(app()->getLocale());
                    @endphp

                    <li class="sf-service-card-wrap">
                        <a class="sf-service-card sf-market-card" href="{{ $serviceUrl }}">
                            <div class="sf-service-card-media">
                                <img
                                    src="{{ $serviceImage }}"
                                    alt="{{ $serviceName }}"
                                    width="720"
                                    height="540"
                                    loading="lazy"
                                    onerror="this.onerror=null;this.src='{{ $fallback }}';"
                                >
                                <span class="sf-service-card-type">{{ __('storefront.product_types.'.$service->product_type->value) }}</span>
                            </div>
                            <div class="sf-service-card-body">
                                <h3>{{ $serviceName }}</h3>
                                <div class="sf-service-card-meta">
                                    @if ($service->vendor)
                                        <span>{{ $vendorName }}</span>
                                    @endif
                                    @if ($cityName)
                                        <span>{{ $cityName }}</span>
                                    @endif
                                    @if ($service->rating_avg)
                                        <span class="sf-service-card-rating" aria-label="{{ number_format((float) $service->rating_avg, 1) }} / 5">★ {{ number_format((float) $service->rating_avg, 1) }}</span>
                                    @endif
                                </div>
                                <div class="sf-service-card-footer">
                                    <strong><small>{{ __('storefront.home.featured.starting_from') }}</small> <bdi dir="ltr">{{ $price }}</bdi></strong>
                                     <span class="sf-service-card-arrow" aria-hidden="true">{{ app()->getLocale() === 'ar' ? '↖' : '↗' }}</span>
                                </div>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif

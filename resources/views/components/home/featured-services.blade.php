@props(['block', 'services' => []])

@php
    $payload = (array) ($block['payload'] ?? []);
    $items = collect($services);
    $filterCategories = $items->pluck('category')->filter()->unique('public_id')->values();
    $storefrontText = app(App\Modules\Shared\Application\Services\StorefrontText::class);
@endphp

@if ($items->isNotEmpty())
    <section class="sf-home-section sf-services-section" data-home-services aria-labelledby="services-heading-{{ $block['public_id'] }}">
        <div class="sf-services-decoration" aria-hidden="true">
            <img src="{{ asset('images/home-hero/balloon-emerald.png') }}" alt="" width="300" height="400" loading="lazy">
            <img src="{{ asset('images/home-hero/balloon-ivory.png') }}" alt="" width="250" height="400" loading="lazy">
        </div>
        <div class="sf-home-container">
            <x-home.section-header
                :id="'services-heading-'.$block['public_id']"
                :eyebrow="__('storefront.home.featured.eyebrow')"
                :title="$payload['title'] ?? __('storefront.home.featured.heading')"
                :description="__('storefront.home.featured.sub')"
                :href="url('/'.app()->getLocale().'/search')"
                :link-label="__('storefront.home.featured.view_all')"
            />

            <div class="sf-service-filters" role="group" aria-label="{{ __('storefront.home.featured.filter_label') }}">
                <button type="button" data-service-filter="" aria-pressed="true">{{ __('storefront.home.featured.all') }}</button>
                @foreach ($filterCategories as $category)
                    @php
                        $filterIcon = match ($category->code) {
                            'cakes', 'cake', 'sale-cakes' => 'heroicon-o-cake',
                            'venues' => 'heroicon-o-map-pin',
                            'photography' => 'heroicon-o-camera',
                            'gifts', 'digital-invitations' => 'heroicon-o-gift',
                            'entertainment' => 'heroicon-o-musical-note',
                            'bouncy-castles', 'rental-bouncy-castles' => 'heroicon-o-face-smile',
                            default => 'heroicon-o-sparkles',
                        };
                    @endphp
                    <button type="button" data-service-filter="{{ $category->public_id }}" aria-pressed="false">
                        <x-dynamic-component :component="$filterIcon" class="sf-service-filter-icon" aria-hidden="true" />
                        {{ $storefrontText->translation($category, 'name') }}
                    </button>
                @endforeach
            </div>

            <ul class="sf-service-rail" role="list" id="service-rail-{{ $block['public_id'] }}">
                @foreach ($items as $service)
                    @php
                        $serviceName = $storefrontText->translation($service, 'name') ?: __('storefront.discovery.services_heading');
                        $categoryName = $service->category ? $storefrontText->translation($service->category, 'name') : '';
                        $vendorName = $service->vendor ? $storefrontText->translation($service->vendor, 'business_name') : '';
                        $cityName = $storefrontText->vendorGovernorates($service->vendor)->pluck('name')->join(' · ');
                        $fallbackPath = app(App\Modules\Shared\Application\Services\StorefrontImageFallback::class)->service(
                            (string) $service->public_id,
                            $service->product_type->value,
                            [(string) ($service->category?->code ?? ''), $categoryName, $serviceName],
                        );
                        $fallback = asset($fallbackPath);
                        $media = $service->getFirstMedia('gallery');
                        // Seeded Picsum photos use this filename and label; real uploads keep their media.
                        $hasDemoPhoto = $media && preg_match('/-photo-\d+\.jpg$/', $media->file_name) && preg_match('/ photo \d+$/', $media->name);
                        $mediaImage = $media ? app(App\Modules\Shared\Application\Services\PublicMediaUrl::class)->for($media, 'medium') : null;
                        $serviceImage = blank($mediaImage) ? $fallback : $mediaImage;
                        $serviceUrl = url('/'.app()->getLocale().'/services/'.$service->public_id);
                        $price = \Brick\Money\Money::ofMinor((int) $service->base_price_minor, (string) $service->base_price_currency)->formatTo(app()->getLocale());
                    @endphp

                    <li class="sf-service-card-wrap" data-service-category="{{ $service->category?->public_id }}">
                        <a class="sf-service-card-favorite" href="{{ route('storefront.account.favorites', ['locale' => app()->getLocale()]) }}" aria-label="{{ __('storefront.home.featured.favorites') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z" /></svg>
                        </a>
                        <a class="sf-service-card sf-market-card" href="{{ $serviceUrl }}">
                            <div class="sf-service-card-media" @if ($hasDemoPhoto) data-reference-photo data-category-code="{{ $service->category?->code }}" @endif>
                                <img
                                    src="{{ $serviceImage }}"
                                    alt="{{ $serviceName }}"
                                    width="720"
                                    height="540"
                                    loading="lazy"
                                    onerror="this.onerror=null;this.src='{{ $fallback }}';"
                                >
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
                                </div>
                                @if ($service->rating_avg && $service->rating_count > 0)
                                    <div class="sf-service-card-rating">
                                        <span aria-hidden="true">★</span>
                                        <strong>{{ number_format((float) $service->rating_avg, 1) }}</strong>
                                        <span class="sf-service-card-reviews">{{ __('storefront.home.featured.reviews', ['count' => $service->rating_count]) }}</span>
                                    </div>
                                @endif
                                <div class="sf-service-card-footer">
                                    <strong><small>{{ __('storefront.home.featured.starting_from') }}</small> <bdi dir="ltr">{{ $price }}</bdi></strong>
                                    <span class="sf-service-card-arrow" aria-hidden="true">{{ app()->getLocale() === 'ar' ? '←' : '→' }}</span>
                                </div>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="sf-service-pagination" role="group" aria-label="{{ __('storefront.home.featured.pages') }}">
                @foreach ($items->chunk(8) as $page => $pageItems)
                    <button type="button" data-service-page="{{ $page }}" aria-controls="service-rail-{{ $block['public_id'] }}" aria-label="{{ __('storefront.home.featured.page', ['number' => $page + 1]) }}" aria-pressed="{{ $page === 0 ? 'true' : 'false' }}"></button>
                @endforeach
            </div>
        </div>
    </section>
@endif

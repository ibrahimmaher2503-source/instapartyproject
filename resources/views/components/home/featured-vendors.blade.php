@props(['vendors' => []])

@php
    $items = collect($vendors);
    $storefrontText = app(App\Modules\Shared\Application\Services\StorefrontText::class);
@endphp

@if ($items->isNotEmpty())
    <section id="homepage-vendors" class="sf-home-section sf-featured-vendors" data-home-vendors aria-labelledby="featured-vendors-heading">
        <div class="sf-vendors-decoration" aria-hidden="true">
            <img src="{{ asset('images/home-hero/balloon-emerald.png') }}" alt="" width="300" height="400" loading="lazy">
            <span>✦</span><span>✦</span><span>✦</span><span>✦</span>
        </div>
        <div class="sf-home-container">
            <x-home.section-header
                id="featured-vendors-heading"
                :eyebrow="__('storefront.home.vendors_strip.eyebrow')"
                :title="__('storefront.home.vendors_strip.heading')"
                :description="__('storefront.home.vendors_strip.sub')"
                :href="url('/'.app()->getLocale().'/vendors')"
                :link-label="__('storefront.home.vendors_strip.view_all')"
            />

            <div class="sf-vendors-carousel">
            <ul class="sf-vendor-rail" id="homepage-vendor-rail" role="list">
                @foreach ($items as $index => $vendor)
                    @php
                        $name = $storefrontText->translation($vendor, 'business_name') ?: __('storefront.vendor.no_services');
                        $city = $storefrontText->vendorGovernorates($vendor)->pluck('name')->join(' · ');
                        $initials = collect(preg_split('/\s+/u', trim($name)) ?: [])
                            ->filter()
                            ->take(2)
                            ->map(static fn (string $part): string => mb_substr($part, 0, 1))
                            ->implode('');
                        $cover = app(App\Modules\Shared\Application\Services\PublicMediaUrl::class)->publicPath($vendor->cover_path);
                        // The development seeder uses these exact paths; uploaded vendor covers stay intact.
                        $referenceCover = in_array($vendor->slug, ['sweet-table-studio', 'joy-rentals-cairo'], true)
                            && $vendor->cover_path === 'vendor-covers/'.$vendor->slug.'-cover.jpg';
                        $profileUrl = route('storefront.vendors.show', ['locale' => app()->getLocale(), 'publicId' => $vendor->slug ?: $vendor->public_id]);
                    @endphp
                    <li>
                        <article class="sf-vendor-card sf-market-card">
                            <a href="{{ $profileUrl }}" class="sf-vendor-card__identity" aria-label="{{ $name }}">
                                <span class="sf-vendor-card__photo" @if ($referenceCover) data-vendor-reference="{{ $vendor->slug }}" @endif>
                                    @if ($cover)
                                        <img src="{{ $cover }}" alt="" width="800" height="380" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                                        <span hidden aria-hidden="true">{{ $initials }}</span>
                                    @else
                                        <span aria-hidden="true">{{ $initials }}</span>
                                    @endif
                                </span>
                            </a>
                            <div class="sf-vendor-card__body">
                                <div>
                                    <h3><a href="{{ $profileUrl }}">{{ $name }}</a></h3>
                                    <p>{{ trans_choice('storefront.vendor.services_count', (int) $vendor->services_count, ['count' => $vendor->services_count]) }}</p>
                                </div>
                                <div class="sf-vendor-card__facts">
                                    @if ($vendor->rating_avg && $vendor->rating_count)
                                        <span class="sf-vendor-card__rating" aria-label="{{ number_format((float) $vendor->rating_avg, 1) }} / 5"><span aria-hidden="true">★</span> {{ number_format((float) $vendor->rating_avg, 1) }} ({{ $vendor->rating_count }})</span>
                                    @endif
                                    @if ($city)<span>{{ $city }}</span>@endif
                                </div>
                                <a class="sf-vendor-card__cta" href="{{ $profileUrl }}" aria-label="{{ __('storefront.vendor.view_profile') }}: {{ $name }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" /></svg>
                                </a>
                            </div>
                        </article>
                    </li>
                @endforeach
            </ul>
            <div class="sf-service-pagination" data-vendor-pages role="group" aria-label="{{ __('storefront.home.featured.pages') }}" data-page-label="{{ __('storefront.home.featured.page', ['number' => ':number']) }}"></div>
            </div>
        </div>
    </section>
@endif

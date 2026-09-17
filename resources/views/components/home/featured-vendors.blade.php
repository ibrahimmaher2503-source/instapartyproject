@props(['vendors' => []])

@php
    $items = collect($vendors)->take(4);
    $storefrontText = app(App\Modules\Shared\Application\Services\StorefrontText::class);
@endphp

@if ($items->isNotEmpty())
    <section class="sf-home-section sf-featured-vendors" aria-labelledby="featured-vendors-heading">
        <div class="sf-home-container">
            <x-home.section-header
                id="featured-vendors-heading"
                :eyebrow="__('storefront.home.vendors_strip.eyebrow')"
                :title="__('storefront.home.vendors_strip.heading')"
                :description="__('storefront.home.vendors_strip.sub')"
                :href="url('/'.app()->getLocale().'/vendors')"
                :link-label="__('storefront.home.vendors_strip.view_all')"
            />

            <ul class="sf-vendor-rail" role="list">
                @foreach ($items as $index => $vendor)
                    @php
                        $name = $storefrontText->translation($vendor, 'business_name') ?: __('storefront.vendor.no_services');
                        $city = $vendor->primaryCity ? $storefrontText->translation($vendor->primaryCity, 'name') : '';
                        $initials = collect(preg_split('/\s+/u', trim($name)) ?: [])
                            ->filter()
                            ->take(2)
                            ->map(static fn (string $part): string => mb_substr($part, 0, 1))
                            ->implode('');
                        $logo = $vendor->logo_path
                            ? Illuminate\Support\Facades\Storage::disk('public')->url($vendor->logo_path)
                            : null;
                        $profileUrl = url('/'.app()->getLocale().'/vendors/'.$vendor->public_id);
                    @endphp
                    <li>
                        <article class="sf-vendor-card sf-market-card">
                            <a href="{{ $profileUrl }}" class="sf-vendor-card__identity" aria-label="{{ $name }}">
                                <span class="sf-vendor-card__avatar">
                                    @if ($logo)
                                        <img src="{{ $logo }}" alt="" width="96" height="96" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                                        <span hidden aria-hidden="true">{{ $initials }}</span>
                                    @else
                                        <span aria-hidden="true">{{ $initials }}</span>
                                    @endif
                                </span>
                                <span class="sf-vendor-card__verified">✓ {{ __('storefront.home.vendors_strip.verified_badge') }}</span>
                            </a>
                            <div class="sf-vendor-card__body">
                                <div>
                                    <h3><a href="{{ $profileUrl }}">{{ $name }}</a></h3>
                                    <p>{{ $city ?: __('storefront.search.filter_location') }}</p>
                                </div>
                                <div class="sf-vendor-card__facts">
                                    <span>{{ trans_choice('storefront.vendor.services_count', (int) $vendor->services_count, ['count' => $vendor->services_count]) }}</span>
                                    @if ($vendor->rating_avg && $vendor->rating_count)
                                        <span aria-label="{{ number_format((float) $vendor->rating_avg, 1) }} / 5">★ {{ number_format((float) $vendor->rating_avg, 1) }} ({{ $vendor->rating_count }})</span>
                                    @endif
                                </div>
                                <a class="sf-vendor-card__cta" href="{{ $profileUrl }}">
                                    {{ __('storefront.vendor.view_profile') }} <span aria-hidden="true">{{ app()->getLocale() === 'ar' ? '←' : '→' }}</span>
                                </a>
                            </div>
                        </article>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif

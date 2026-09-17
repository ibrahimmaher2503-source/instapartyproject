@props(['packages'])

@php
    $imageFallbacks = app(App\Modules\Shared\Application\Services\StorefrontImageFallback::class);
@endphp

<section class="sf-home-section sf-packages-section" aria-labelledby="packages-heading">
    <div class="sf-home-container">
        <div class="sf-packages-panel">
            <x-home.section-header
                id="packages-heading"
                :eyebrow="__('storefront.home.packages.eyebrow')"
                :title="__('storefront.home.packages.heading')"
                :description="__('storefront.home.packages.sub')"
                :href="url('/'.app()->getLocale().'/search')"
                :link-label="__('storefront.home.packages.view_all')"
            />

            <div class="sf-package-rail">
                @foreach (collect($packages)->take(3) as $index => $package)
                    @php
                        $query = $package['occasion_public_id'] ? '?occasion='.$package['occasion_public_id'] : '';
                        $fallback = asset($imageFallbacks->service(
                            (string) $package['public_id'],
                            'rental',
                            [(string) $package['name'], (string) $package['description']],
                        ));
                        $image = filled($package['hero_url']) ? $package['hero_url'] : $fallback;
                    @endphp
                    <a class="sf-package-card sf-package-card--{{ ($index % 3) + 1 }} sf-market-card" href="{{ url('/'.app()->getLocale().'/search').$query }}">
                        <img
                            src="{{ $image }}"
                            alt=""
                            width="720"
                            height="540"
                            loading="lazy"
                            onerror="this.onerror=null;this.src='{{ $fallback }}';"
                        >
                        <span class="sf-package-card__wash" aria-hidden="true"></span>
                        <span class="sf-package-card__content">
                            <strong>{{ $package['name'] }}</strong>
                            <span>{{ $package['description'] }}</span>
                            @if ($package['min_budget'] || $package['max_budget'])
                                <small>
                                    {{ $package['min_budget'] && $package['max_budget']
                                        ? __('storefront.home.packages.budget_range', ['min' => $package['min_budget'], 'max' => $package['max_budget']])
                                        : __('storefront.home.packages.budget_from', ['amount' => $package['min_budget'] ?? $package['max_budget']]) }}
                                </small>
                            @endif
                            <b>{{ __('storefront.home.packages.explore') }} <span aria-hidden="true">{{ app()->getLocale() === 'ar' ? '←' : '→' }}</span></b>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</section>

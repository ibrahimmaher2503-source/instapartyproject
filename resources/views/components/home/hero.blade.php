@props([
    'block',
    'fallbackImageUrl' => null,
])

@php
    $slides = collect((array) data_get($block, 'payload.slides', []))
        ->filter(static fn (mixed $slide): bool => is_array($slide))
        ->values();
    $slideCount = $slides->count();
    $fallbackImage = $fallbackImageUrl ?: asset('images/homepage-scenes/hero.png');
@endphp

@if ($slides->isNotEmpty())
    <section class="sf-hero" aria-label="{{ __('storefront.home.hero_label') }}" data-home-hero>
        <div class="sf-hero-frame">
            <div class="sf-hero-content">
                @foreach ($slides as $slideIndex => $slide)
                    @php
                        $imageUrl = filled($slide['image_url'] ?? null) ? $slide['image_url'] : $fallbackImage;
                        $isVisible = $slideIndex === 0;
                    @endphp

                    <article
                        @class(['sf-hero-slide', 'hidden' => ! $isVisible])
                        data-home-hero-slide="{{ $slideIndex }}"
                        aria-hidden="{{ $isVisible ? 'false' : 'true' }}"
                    >
                        @if (filled($slide['eyebrow'] ?? null))
                            <p class="sf-hero-eyebrow">{{ $slide['eyebrow'] }}</p>
                        @endif

                        <h1 class="sf-hero-title">{{ $slide['headline'] ?? __('storefront.home.hero.headline') }}</h1>

                        @if (filled($slide['sub'] ?? null))
                            <p class="sf-hero-description">{{ $slide['sub'] }}</p>
                        @endif

                        @if (filled($slide['cta_label'] ?? null) && filled($slide['cta_url'] ?? null))
                            @php
                                $ctaUrl = (string) $slide['cta_url'];
                                $ctaHref = str_starts_with($ctaUrl, '/') ? url('/'.app()->getLocale().$ctaUrl) : $ctaUrl;
                            @endphp
                            <a href="{{ $ctaHref }}" class="sf-hero-cta">
                                {{ $slide['cta_label'] }}
                                <svg class="sf-hero-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" d="M5 12h13m-5-5 5 5-5 5" />
                                </svg>
                            </a>
                        @endif

                    </article>
                @endforeach
            </div>

            <div class="sf-hero-scene" aria-hidden="true">
                @foreach ($slides as $slideIndex => $slide)
                    @php
                        $imageUrl = filled($slide['image_url'] ?? null) ? $slide['image_url'] : $fallbackImage;
                        $isVisible = $slideIndex === 0;
                    @endphp
                    <div @class(['sf-hero-art', 'hidden' => ! $isVisible]) data-home-hero-art="{{ $slideIndex }}">
                        <div class="sf-hero-orbit sf-hero-orbit--large"></div>
                        <div class="sf-hero-orbit sf-hero-orbit--small"></div>
                        <span class="sf-hero-spark sf-hero-spark--one">✦</span>
                        <span class="sf-hero-spark sf-hero-spark--two">✦</span>
                        <img
                            src="{{ $imageUrl }}"
                            alt=""
                            class="sf-hero-image"
                            width="960"
                            height="720"
                            fetchpriority="{{ $isVisible ? 'high' : 'auto' }}"
                            loading="{{ $isVisible ? 'eager' : 'lazy' }}"
                            onerror="this.onerror=null;this.src='{{ $fallbackImage }}';"
                        >
                    </div>
                @endforeach
            </div>
        </div>

        @if ($slideCount > 1)
            <div class="sf-hero-controls" aria-label="{{ __('storefront.common.pagination') }}">
                <button type="button" class="sf-hero-control" data-home-hero-prev aria-label="{{ __('storefront.common.previous') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" /></svg>
                </button>
                <div class="sf-hero-dots">
                    @foreach ($slides as $slideIndex => $slide)
                        <button type="button" class="sf-hero-dot" data-home-hero-dot="{{ $slideIndex }}" aria-label="{{ $slideIndex + 1 }}" aria-current="{{ $slideIndex === 0 ? 'true' : 'false' }}"></button>
                    @endforeach
                </div>
                <button type="button" class="sf-hero-control" data-home-hero-next aria-label="{{ __('storefront.common.next') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                </button>
            </div>
        @endif

        @if ($slot->isNotEmpty())
            <div class="sf-hero-search">{{ $slot }}</div>
        @endif
    </section>
@endif

@props(['block'])

@php
    $payload = (array) ($block['payload'] ?? []);
    $imageUrl = filled($payload['image_url'] ?? null) ? $payload['image_url'] : null;
    $ctaUrl = (string) ($payload['cta_url'] ?? '/wizard');
    $ctaHref = str_starts_with($ctaUrl, '/') ? url('/'.app()->getLocale().$ctaUrl) : $ctaUrl;
@endphp

@if (filled($payload['headline'] ?? null))
    <section class="sf-home-section sf-cta-section" aria-labelledby="cta-heading-{{ $block['public_id'] }}">
        <div class="sf-home-container">
            <div class="sf-cta-panel">
                <div class="sf-cta-copy">
                    <p class="sf-section-eyebrow sf-section-eyebrow--light">{{ __('storefront.home.final_cta.eyebrow') }}</p>
                    <h2 id="cta-heading-{{ $block['public_id'] }}">{{ $payload['headline'] }}</h2>
                    <a class="sf-button sf-button--light" href="{{ $ctaHref }}">
                        {{ $payload['cta_label'] ?? __('storefront.home.final_cta.primary_cta') }}
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
                @if ($imageUrl)
                    <img class="sf-cta-image" src="{{ $imageUrl }}" alt="" width="720" height="420" loading="lazy">
                @else
                    <div class="sf-cta-orbit" aria-hidden="true"><span></span><span></span><span></span></div>
                @endif
            </div>
        </div>
    </section>
@endif

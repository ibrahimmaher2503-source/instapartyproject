@props(['block'])

@php
    $payload = (array) ($block['payload'] ?? []);
    $ctaUrl = (string) ($payload['cta_url'] ?? '');
    $ctaHref = str_starts_with($ctaUrl, '/') ? url('/'.app()->getLocale().$ctaUrl) : $ctaUrl;
@endphp

@if (filled($payload['headline'] ?? null))
    <section class="sf-home-section sf-loyalty-section" aria-labelledby="loyalty-heading-{{ $block['public_id'] }}">
        <div class="sf-home-container">
            <div class="sf-loyalty-panel">
                <span class="sf-loyalty-seal" aria-hidden="true">✦</span>
                <div>
                    <p class="sf-section-eyebrow">{{ __('storefront.home.final_cta.eyebrow') }}</p>
                    <h2 id="loyalty-heading-{{ $block['public_id'] }}">{{ $payload['headline'] }}</h2>
                    @if (filled($payload['body'] ?? null))
                        <p>{{ $payload['body'] }}</p>
                    @endif
                </div>
                @if ($ctaHref !== '')
                    <a class="sf-button sf-button--navy" href="{{ $ctaHref }}">{{ __('storefront.home.final_cta.secondary_cta') }} <span aria-hidden="true">↗</span></a>
                @endif
            </div>
        </div>
    </section>
@endif

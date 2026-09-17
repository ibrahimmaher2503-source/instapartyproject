@props(['block'])

@php
    $payload = (array) ($block['payload'] ?? []);
    $imageSide = ($payload['image_side'] ?? 'right') === 'left' ? 'is-image-first' : 'is-copy-first';
@endphp

@if (filled($payload['headline'] ?? null) && filled($payload['image_url'] ?? null))
    <section class="sf-home-section sf-story-section" aria-labelledby="story-heading-{{ $block['public_id'] }}">
        <div class="sf-home-container">
            <div class="sf-story-grid {{ $imageSide }}">
                <div class="sf-story-media">
                    <img src="{{ $payload['image_url'] }}" alt="" width="900" height="680" loading="lazy">
                </div>
                <div class="sf-story-copy">
                    <p class="sf-section-eyebrow">{{ __('storefront.home.trust.eyebrow') }}</p>
                    <h2 id="story-heading-{{ $block['public_id'] }}">{{ $payload['headline'] }}</h2>
                    @if (filled($payload['body'] ?? null))
                        <p>{{ $payload['body'] }}</p>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif

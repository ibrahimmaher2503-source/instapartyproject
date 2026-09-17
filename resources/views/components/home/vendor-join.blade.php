@props(['block'])

@php
    $payload = (array) ($block['payload'] ?? []);
    $ctaHref = route('filament.vendor.auth.register', ['lang' => app()->getLocale()]);
    $imageUrl = filled($payload['image_url'] ?? null)
        ? (string) $payload['image_url']
        : asset('images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (10).png');
@endphp

<section class="sf-home-section sf-vendor-join-section" aria-labelledby="vendor-join-heading-{{ $block['public_id'] }}">
    <div class="sf-home-container">
        <div class="sf-vendor-join-panel">
            <div class="sf-vendor-join-copy">
                <p class="sf-section-eyebrow">{{ __('storefront.join_us.eyebrow') }}</p>
                <h2 id="vendor-join-heading-{{ $block['public_id'] }}">{{ $payload['headline'] ?? __('storefront.join_us.title') }}</h2>
                <p class="sf-vendor-join-body">{{ $payload['body'] ?? __('storefront.join_us.intro') }}</p>
                <div class="sf-vendor-join-actions">
                    <a class="sf-button sf-button--navy" href="{{ $ctaHref }}">
                        {{ $payload['cta_label'] ?? __('storefront.join_us.primary_cta') }}
                        <span aria-hidden="true">↗</span>
                    </a>
                    <span class="sf-vendor-join-note">{{ __('storefront.join_us.trust_note') }}</span>
                </div>
            </div>

            <div class="sf-vendor-join-scene" aria-hidden="true">
                <span class="sf-vendor-join-spark sf-vendor-join-spark--one">✦</span>
                <span class="sf-vendor-join-spark sf-vendor-join-spark--two">✦</span>
                <div class="sf-vendor-join-orbit"></div>
                <img src="{{ $imageUrl }}" alt="" width="520" height="520" loading="lazy">
            </div>
        </div>
    </div>
</section>

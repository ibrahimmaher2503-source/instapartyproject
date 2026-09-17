@props(['block'])

@php
    $payload = (array) ($block['payload'] ?? []);
    $vendorId = (string) ($payload['vendor_public_id'] ?? '');
@endphp

@if ($vendorId !== '')
    <section class="sf-home-section sf-vendor-section" aria-labelledby="vendor-heading-{{ $block['public_id'] }}">
        <div class="sf-home-container">
            <div class="sf-vendor-panel">
                <div>
                    <p class="sf-section-eyebrow sf-section-eyebrow--light">{{ __('storefront.home.vendors_strip.eyebrow') }}</p>
                    <h2 id="vendor-heading-{{ $block['public_id'] }}">{{ $payload['title'] ?? __('storefront.home.vendors_strip.heading') }}</h2>
                    <p>{{ __('storefront.home.vendors_strip.sub') }}</p>
                </div>
                <a class="sf-button sf-button--light" href="{{ url('/'.app()->getLocale().'/vendors/'.$vendorId) }}">
                    {{ __('storefront.home.vendors_strip.view_all') }}
                    <span aria-hidden="true">↗</span>
                </a>
            </div>
        </div>
    </section>
@endif

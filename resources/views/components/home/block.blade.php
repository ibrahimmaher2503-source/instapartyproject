@props([
    'block',
    'services' => [],
    'categories' => [],
    'occasions' => [],
])

@php
    $payload = (array) ($block['payload'] ?? []);
@endphp

@switch($block['block_type'])
    @case('featured_services')
        <x-home.featured-services :block="$block" :services="$services" />
        @break
    @case('featured_occasions')
        <x-home.taxonomy :block="$block" :items="$occasions" kind="occasion" />
        @break
    @case('featured_categories')
        <x-home.taxonomy :block="$block" :items="$categories" kind="category" />
        @break
    @case('vendor_spotlight')
        <x-home.vendor-spotlight :block="$block" />
        @break
    @case('vendor_join')
        <x-home.vendor-join :block="$block" />
        @break
    @case('cta_banner')
        <x-home.cta-banner :block="$block" />
        @break
    @case('text_image_split')
        <x-home.text-image :block="$block" />
        @break
    @case('testimonials')
        <x-home.testimonials :block="$block" />
        @break
    @case('loyalty_promo')
        <x-home.loyalty :block="$block" />
        @break
@endswitch

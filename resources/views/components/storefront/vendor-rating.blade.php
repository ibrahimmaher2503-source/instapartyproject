@props(['vendor'])

<x-storefront.rating-display
    :average="$vendor->rating_avg"
    :count="$vendor->rating_count"
    {{ $attributes }}
/>

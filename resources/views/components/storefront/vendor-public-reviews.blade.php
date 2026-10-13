@props(['summary' => null])

@php
    $summary ??= ['average' => 0, 'count' => 0, 'featured_review' => null];
    $featuredReview = $summary['featured_review'] ?? null;
@endphp

<div {{ $attributes->class(['space-y-1']) }}>
    <x-storefront.rating-display
        :average="$summary['average'] > 0 ? $summary['average'] : null"
        :count="$summary['count']"
    />

    @if ($featuredReview)
        <blockquote data-vendor-featured-review class="space-y-1 text-xs text-current/80">
            <p class="line-clamp-2">{{ $featuredReview['body'] }}</p>
            <footer>
                <span data-vendor-featured-review-rating aria-label="{{ trans_choice('storefront.service.rating_aria', 1, ['avg' => number_format((float) $featuredReview['rating'], 1), 'count' => 1]) }}">
                    <bdi dir="ltr">★ {{ $featuredReview['rating'] }} / 5</bdi>
                </span>
            </footer>
        </blockquote>
    @endif
</div>

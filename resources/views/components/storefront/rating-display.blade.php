@props(['average' => null, 'count' => 0, 'showEmpty' => true])

@if ((int) $count > 0 && $average !== null)
    <span {{ $attributes->class(['inline-flex items-center gap-1 text-sm font-semibold']) }} aria-label="{{ trans_choice('storefront.service.rating_aria', (int) $count, ['avg' => number_format((float) $average, 1), 'count' => (int) $count]) }}">
        <span aria-hidden="true">★</span>
        <bdi dir="ltr">{{ number_format((float) $average, 1) }}</bdi>
        <span class="font-normal">· {{ trans_choice('storefront.service.reviews_count', (int) $count, ['count' => (int) $count]) }}</span>
    </span>
@elseif ($showEmpty)
    <span {{ $attributes->class(['text-sm text-ink-500']) }}>{{ __('storefront.service.no_reviews') }}</span>
@endif

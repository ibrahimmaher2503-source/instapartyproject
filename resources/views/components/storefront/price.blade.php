@props(['minor', 'currency'])

@php
    $formattedPrice = Brick\Money\Money::ofMinor((int) $minor, (string) $currency)->formatTo(app()->getLocale());
@endphp

<bdi dir="ltr" {{ $attributes }}>{{ $formattedPrice }}</bdi>

@props(['text'])

@php
    $segments = preg_split(
        '/((?:[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})|(?:\+?[0-9][0-9\s()\-]{6,}[0-9]))/iu',
        (string) $text,
        -1,
        PREG_SPLIT_DELIM_CAPTURE
    ) ?: [];
@endphp

<span {{ $attributes }}>
    @foreach ($segments as $segment)
        @if (preg_match('/^(?:[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}|\+?[0-9][0-9\s()\-]{6,}[0-9])$/iu', $segment) === 1)
            <bdi dir="ltr">{{ $segment }}</bdi>
        @else
            {{ $segment }}
        @endif
    @endforeach
</span>

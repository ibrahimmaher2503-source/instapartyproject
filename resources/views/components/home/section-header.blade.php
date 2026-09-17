@props([
    'id',
    'eyebrow' => null,
    'title',
    'description' => null,
    'href' => null,
    'linkLabel' => null,
    'align' => 'split',
])

<div @class(['sf-section-header', 'sf-section-header--center' => $align === 'center'])>
    <div class="sf-section-header__copy">
        @if (filled($eyebrow))
            <p class="sf-section-eyebrow">{{ $eyebrow }}</p>
        @endif
        <h2 id="{{ $id }}" class="sf-section-title">{{ $title }}</h2>
        @if (filled($description))
            <p class="sf-section-subtitle">{{ $description }}</p>
        @endif
    </div>

    @if (filled($href) && filled($linkLabel))
        <a class="sf-text-link" href="{{ $href }}">
            {{ $linkLabel }}
            <span aria-hidden="true">{{ app()->getLocale() === 'ar' ? '←' : '→' }}</span>
        </a>
    @endif
</div>

@props(['block'])

@php
    $payload = (array) ($block['payload'] ?? []);
    $items = collect($payload['items'] ?? [])->filter(static fn ($item): bool => is_array($item) && filled($item['quote'] ?? null));
@endphp

@if ($items->isNotEmpty())
    <section class="sf-home-section sf-testimonials-section" aria-labelledby="testimonials-heading-{{ $block['public_id'] }}">
        <div class="sf-home-container">
            <div class="sf-section-heading">
                <p class="sf-section-eyebrow">{{ __('storefront.home.trust.eyebrow') }}</p>
                <h2 id="testimonials-heading-{{ $block['public_id'] }}" class="sf-section-title">{{ $payload['title'] ?? __('storefront.home.trust.heading') }}</h2>
            </div>
            <div class="sf-testimonial-grid">
                @foreach ($items as $index => $item)
                    <figure class="sf-testimonial sf-testimonial--{{ $index % 3 }}">
                        <div class="sf-quote-mark" aria-hidden="true">“</div>
                        <blockquote>{{ $item['quote'] }}</blockquote>
                        <figcaption>
                            <span class="sf-avatar" aria-hidden="true">{{ str((string) ($item['author'] ?? 'I'))->substr(0, 1) }}</span>
                            <span>{{ $item['author'] ?? __('storefront.common.site_name') }}</span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
@endif

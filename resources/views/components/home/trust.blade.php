<section class="sf-home-section sf-trust-section" aria-labelledby="trust-heading">
    <div class="sf-home-container">
        <div class="sf-section-heading">
            <p class="sf-section-eyebrow">{{ __('storefront.home.trust.eyebrow') }}</p>
            <h2 id="trust-heading" class="sf-section-title">{{ __('storefront.home.trust.heading') }}</h2>
        </div>
        <div class="sf-trust-grid">
            @foreach (['secure', 'confirmation', 'pricing', 'support'] as $trust)
                <article class="sf-trust-item">
                    <span class="sf-trust-icon" aria-hidden="true">{{ ['⌁', '✓', '₤', '◌'][$loop->index] }}</span>
                    <h3>{{ __('storefront.home.trust.'.$trust.'_title') }}</h3>
                    <p>{{ __('storefront.home.trust.'.$trust.'_sub') }}</p>
                </article>
            @endforeach
        </div>
        <a class="sf-text-link" href="{{ url('/'.app()->getLocale().'/about/trust') }}">{{ __('storefront.home.trust.learn_more') }}</a>
    </div>
</section>

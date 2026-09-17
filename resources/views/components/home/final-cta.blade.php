<section class="sf-home-section sf-final-section" aria-labelledby="final-heading">
    <div class="sf-home-container">
        <div class="sf-final-panel">
            <div class="sf-final-copy">
                <p class="sf-section-eyebrow sf-section-eyebrow--light">{{ __('storefront.home.final_cta.eyebrow') }}</p>
                <h2 id="final-heading">{{ __('storefront.home.final_cta.heading') }}</h2>
                <p>{{ __('storefront.home.final_cta.sub') }}</p>
                <div class="sf-final-actions">
                    <a class="sf-button sf-button--light" href="{{ url('/'.app()->getLocale().'/wizard') }}">{{ __('storefront.home.final_cta.primary_cta') }} <span aria-hidden="true">&rarr;</span></a>
                    <a class="sf-button sf-button--quiet" href="{{ url('/'.app()->getLocale().'/search') }}">{{ __('storefront.home.final_cta.secondary_cta') }}</a>
                </div>
            </div>
            <div class="sf-final-media" aria-hidden="true">
                <img class="sf-final-scene" src="{{ asset(config('storefront.closing_scene')) }}" alt="" width="1672" height="941">
            </div>
        </div>
    </div>
</section>

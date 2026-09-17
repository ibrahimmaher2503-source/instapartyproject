<section class="sf-home-section sf-how-section" aria-labelledby="how-heading">
    <div class="sf-home-container">
        <x-home.section-header
            id="how-heading"
            :eyebrow="__('storefront.home.how_it_works.eyebrow')"
            :title="__('storefront.home.how_it_works.heading')"
            align="center"
        />
        <ol class="sf-how-grid">
            @foreach (['step1', 'step2', 'step3', 'step4'] as $index => $step)
                <li class="sf-how-step">
                    <span class="sf-how-number" aria-hidden="true">0{{ $index + 1 }}</span>
                    <h3>{{ __('storefront.home.how_it_works.'.$step.'_title') }}</h3>
                    <p>{{ __('storefront.home.how_it_works.'.$step.'_sub') }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

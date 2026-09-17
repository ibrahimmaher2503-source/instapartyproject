@php
    $signupUrl = route('filament.vendor.auth.register', ['lang' => app()->getLocale()]);
    $categories = app(App\Modules\Catalog\Application\Actions\ListPublicCategoriesAction::class)->execute();
    $text = app(App\Modules\Shared\Application\Services\StorefrontText::class);
@endphp
<div class="vendor-join">
    <section class="vendor-join__hero" aria-labelledby="vendor-title">
        <div class="vendor-join__container vendor-join__hero-grid">
            <div>
                <p class="vendor-join__eyebrow">{{ __('storefront.join_us.eyebrow') }}</p>
                <h1 id="vendor-title">{{ __('storefront.join_us.title') }}</h1>
                <p class="vendor-join__intro">{{ __('join-vendor.intro') }}</p>
                <a class="vendor-join__button" href="{{ $signupUrl }}">{{ __('storefront.join_us.primary_cta') }}</a>
                <p class="vendor-join__trust"><span aria-hidden="true">✓</span> {{ __('storefront.join_us.trust_note') }}</p>
                <a class="vendor-join__login" href="{{ route('filament.vendor.auth.login', ['lang' => app()->getLocale()]) }}">{{ __('join-vendor.login') }}</a>
            </div>
            <img class="vendor-join__illustration" src="{{ asset('images/vendor-marketplace.svg') }}" alt="{{ __('join-vendor.art_alt') }}" width="600" height="480" fetchpriority="high">
        </div>
    </section>
    <section class="vendor-join__section vendor-join__container" aria-labelledby="vendor-benefits">
        <div class="vendor-join__heading"><h2 id="vendor-benefits">{{ __('join-vendor.benefits_title') }}</h2><p>{{ __('join-vendor.benefits_intro') }}</p></div>
        <div class="vendor-join__benefits">
            @foreach (__('join-vendor.benefits') as $key => $benefit)
                <article>
                    <span class="vendor-join__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            @switch($key)
                                @case('services') <path d="M4 9h16v11H4zM3 9l2-6h14l2 6M9 20v-7h6v7M3 9c0 3 4 3 4 0 0 3 5 3 5 0 0 3 5 3 5 0 0 3 4 3 4 0"/> @break
                                @case('requests') <path d="M5 4h14v12H9l-4 4zM8 8h8M8 12h5"/> @break
                                @case('bookings') <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18m-13 5 3 3 5-5"/> @break
                                @default <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 15h7M14 20h7"/>
                            @endswitch
                        </svg>
                    </span>
                    <h3>{{ $benefit['title'] }}</h3><p>{{ $benefit['body'] }}</p>
                </article>
            @endforeach
        </div>
    </section>
    @if ($categories->isNotEmpty())
        <section class="vendor-join__categories" aria-labelledby="vendor-categories">
            <div class="vendor-join__container vendor-join__split">
                <div class="vendor-join__heading"><h2 id="vendor-categories">{{ __('join-vendor.categories_title') }}</h2><p>{{ __('join-vendor.categories_intro') }}</p></div>
                <ul class="vendor-join__chips" role="list">
                    @foreach ($categories as $category)
                        <li><a href="{{ url('/'.app()->getLocale().'/c/'.$category->code) }}">{{ $text->translation($category, 'name') }}</a></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
    <section class="vendor-join__section vendor-join__container" aria-labelledby="vendor-process">
        <div class="vendor-join__heading"><p class="vendor-join__eyebrow">{{ __('storefront.join_us.steps_eyebrow') }}</p><h2 id="vendor-process">{{ __('storefront.join_us.steps_title') }}</h2></div>
        <ol class="vendor-join__timeline">
            @foreach (__('join-vendor.steps') as $step)
                <li><span class="vendor-join__number">{{ $loop->iteration }}</span><div><h3>{{ $step['title'] }}</h3><p>{{ $step['body'] }}</p></div></li>
            @endforeach
        </ol>
    </section>
    <section class="vendor-join__requirements" aria-labelledby="vendor-requirements">
        <div class="vendor-join__container vendor-join__split">
            <div class="vendor-join__heading"><p class="vendor-join__eyebrow">{{ __('join-vendor.requirements_eyebrow') }}</p><h2 id="vendor-requirements">{{ __('join-vendor.requirements_title') }}</h2><p>{{ __('join-vendor.requirements_intro') }}</p></div>
            <div class="vendor-join__checklist">
                @foreach (__('join-vendor.requirements') as $requirement)
                    <div><span aria-hidden="true">✓</span><p>{{ $requirement }}</p></div>
                @endforeach
                <p class="vendor-join__note">{{ __('join-vendor.requirements_note') }}</p>
            </div>
        </div>
    </section>
    <section class="vendor-join__section vendor-join__container vendor-join__split" aria-labelledby="vendor-faq">
        <div class="vendor-join__heading"><h2 id="vendor-faq">{{ __('join-vendor.faq_title') }}</h2><p>{{ __('join-vendor.faq_intro') }}</p></div>
        <div class="vendor-join__faq">
            @foreach (__('join-vendor.faq') as $faq)
                <details><summary>{{ $faq['question'] }}<span aria-hidden="true">+</span></summary><p>{{ $faq['answer'] }}</p></details>
            @endforeach
        </div>
    </section>
    <section class="vendor-join__closing" aria-labelledby="vendor-closing">
        <div class="vendor-join__container">
            <div><p class="vendor-join__eyebrow">{{ __('join-vendor.closing_eyebrow') }}</p><h2 id="vendor-closing">{{ __('join-vendor.closing_title') }}</h2><p>{{ __('join-vendor.closing_intro') }}</p></div>
            <a class="vendor-join__button" href="{{ $signupUrl }}">{{ __('storefront.join_us.primary_cta') }}</a>
        </div>
    </section>
</div>

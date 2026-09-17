@extends('storefront.layouts.app')

@php
    $page = $cmsPage
        ? [
            'title' => $cmsPage->getTranslation('title', app()->getLocale(), useFallbackLocale: true),
            'intro' => $cmsPage->getTranslation('meta_description', app()->getLocale(), useFallbackLocale: true),
            'body' => $cmsPage->getTranslation('body', app()->getLocale(), useFallbackLocale: true),
        ]
        : match ($slug) {
            'faq' => __('storefront.pages.faq'),
            'trust' => __('storefront.pages.trust'),
        };
    $body = trim(strip_tags(preg_replace('/<br\s*\/?\s*>/i', "\n", (string) ($page['body'] ?? ''))));
    $contactConfigurationPending = false;

    if ($cmsPage && $slug === 'contact') {
        $branding = app(App\Modules\Shared\Application\Actions\GetBrandingAction::class)->execute(app()->getLocale());
        $body = (string) $page['body'];

        foreach ([
            'support@instaparty.local' => $branding['support_email'],
            '+20 100 000 0000' => $branding['support_phone'],
            '+201000000000' => $branding['support_phone'],
        ] as $placeholder => $configuredValue) {
            if (! str_contains(strtolower($body), strtolower($placeholder))) {
                continue;
            }

            if (filled($configuredValue)) {
                $body = str_ireplace($placeholder, (string) $configuredValue, $body);
            } else {
                $body = preg_replace('/^.*'.preg_quote($placeholder, '/').'.*$(?:\R)?/mi', '', $body) ?? $body;
                $contactConfigurationPending = true;
            }
        }

        $page['body'] = $body;
        $body = trim(strip_tags(preg_replace('/<br\s*\/?\s*>/i', "\n", $body)));
    }

    $bodyBlocks = array_values(array_filter(
        preg_split('/\R{2,}/', $body) ?: [],
        static fn (string $block): bool => trim($block) !== '',
    ));

    $faqSections = $cmsPage
        ? collect($bodyBlocks)->chunk(2)->map(static fn ($blocks): array => [
            'title' => (string) ($blocks->values()->get(0) ?? ''),
            'body' => (string) ($blocks->values()->get(1) ?? ''),
        ])->filter(static fn (array $section): bool => $section['title'] !== '' && $section['body'] !== '')->values()->all()
        : ($page['sections'] ?? []);

    if ($contactConfigurationPending) {
        array_shift($bodyBlocks);
    }
@endphp

@section('title', $page['title'].' · '.__('storefront.common.site_name'))
@section('meta_description', $page['intro'] ?? '')

@section('content')
    @if ($slug === 'faq')
        <div class="sf-cms-page sf-faq-page" data-faq-page>
            <section class="sf-faq-hero">
                <div class="sf-faq-hero__inner sf-faq-container">
                    <div class="sf-faq-hero__copy">
                        <p class="sf-faq-eyebrow">{{ __('storefront.common.site_name') }}</p>
                        <h1>{{ $page['title'] }}</h1>
                        @if (filled($page['intro'] ?? null))
                            <p class="sf-faq-hero__description">{{ $page['intro'] }}</p>
                        @endif
                    </div>

                    <form class="sf-faq-search" role="search" data-faq-search-form>
                        <label for="faq-search">{{ __('storefront.pages.faq.search_label') }}</label>
                        <div class="sf-faq-search__control">
                            <svg class="sf-faq-search__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="10.75" cy="10.75" r="5.75" />
                                <path stroke-linecap="round" d="m15.25 15.25 4.25 4.25" />
                            </svg>
                            <input id="faq-search" name="q" type="search" autocomplete="off" placeholder="{{ __('storefront.pages.faq.search_placeholder') }}" data-faq-search-input>
                            <button type="button" class="sf-faq-search__clear" aria-label="{{ __('storefront.pages.faq.clear_search') }}" data-faq-search-clear hidden>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" />
                                </svg>
                            </button>
                        </div>
                        <p class="sf-faq-search__status" data-faq-search-status aria-live="polite"></p>
                    </form>
                </div>
            </section>

            <section class="sf-faq-content sf-faq-container" aria-labelledby="faq-list-title">
                <div class="sf-faq-layout">
                    <div class="sf-faq-main">
                        <div class="sf-faq-section-heading">
                            <div>
                                <p class="sf-faq-section-eyebrow">{{ __('storefront.pages.faq.section_eyebrow') }}</p>
                                <h2 id="faq-list-title">{{ __('storefront.pages.faq.list_title') }}</h2>
                            </div>
                            <span class="sf-faq-count" data-faq-count data-faq-count-template="{{ __('storefront.pages.faq.results_count') }}"></span>
                        </div>

                        <div class="sf-faq-list" data-faq-list>
                            @foreach ($faqSections as $section)
                                <details class="sf-faq-item" data-faq-item data-faq-search="{{ $section['title'].' '.$section['body'] }}">
                                    <summary>
                                        <span class="sf-faq-item__question">{{ $section['title'] }}</span>
                                        <span class="sf-faq-item__toggle" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m6.5 9 5.5 5.5L17.5 9" />
                                            </svg>
                                        </span>
                                    </summary>
                                    <div class="sf-faq-item__answer">
                                        <p>{{ $section['body'] }}</p>
                                    </div>
                                </details>
                            @endforeach
                        </div>

                        <div class="sf-faq-empty" data-faq-empty hidden>
                            <span class="sf-faq-empty__mark" aria-hidden="true">?</span>
                            <h2>{{ __('storefront.pages.faq.empty_title') }}</h2>
                            <p>{{ __('storefront.pages.faq.empty_body') }}</p>
                        </div>

                        <section class="sf-faq-cta" aria-labelledby="faq-support-title">
                            <div class="sf-faq-cta__mark" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 10.5h7.5m-7.5 3h4.5M20 11.25a7.25 7.25 0 0 1-7.25 7.25H9.5L5 21v-3.63a7.24 7.24 0 0 1-2.25-5.37A7.25 7.25 0 0 1 10 4.75h2.75A7.25 7.25 0 0 1 20 11.25Z" />
                                </svg>
                            </div>
                            <div>
                                <h2 id="faq-support-title">{{ __('storefront.pages.faq.support_title') }}</h2>
                                <p>{{ __('storefront.pages.faq.support_body') }}</p>
                            </div>
                            <a href="{{ url('/'.app()->getLocale().'/p/contact') }}" class="sf-faq-cta__link">
                                {{ __('storefront.pages.faq.support_cta') }}
                                <span aria-hidden="true">→</span>
                            </a>
                        </section>
                    </div>
                </div>
            </section>
        </div>
    @elseif ($slug === 'contact')
        @php
            $hasDirectContact = filled($branding['support_email']) || filled($branding['support_phone']);
            $sellerCopy = $bodyBlocks[1] ?? __('storefront.pages.contact.seller_body');
        @endphp
        <div class="sf-cms-page sf-contact-page bg-[var(--sf-ivory)]">
            <section class="sf-contact-hero relative overflow-hidden bg-secondary-900 text-white">
                <div class="pointer-events-none absolute inset-y-6 start-0 w-24 rounded-e-full border border-white/[0.03] bg-white/[0.015] sm:inset-y-8 sm:w-40" aria-hidden="true"></div>
                <div class="sf-contact-hero__inner relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <p class="text-sm font-bold uppercase tracking-[0.18em] text-secondary-300">{{ __('storefront.pages.contact.eyebrow') }}</p>
                    <h1 class="mt-2 text-3xl font-extrabold tracking-[-0.04em] sm:text-4xl">{{ $page['title'] }}</h1>
                    @if (filled($page['intro'] ?? null))
                        <p class="mt-3 max-w-2xl text-base leading-7 text-white/70">{{ $page['intro'] }}</p>
                    @endif
                </div>
            </section>

            <main class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8 lg:py-14">
                <div class="grid gap-6 xl:grid-cols-[1.35fr_0.8fr] xl:gap-8">
                    <section aria-labelledby="contact-support-title" class="rounded-[1.25rem] border border-ink-200 bg-white p-6 shadow-sm sm:p-8">
                        <p class="text-sm font-bold text-secondary-700">{{ __('storefront.pages.contact.section_eyebrow') }}</p>
                        <h2 id="contact-support-title" class="mt-2 text-3xl font-extrabold tracking-[-0.025em] text-ink-900">{{ __('storefront.pages.contact.support_title') }}</h2>
                        <p class="mt-3 max-w-2xl text-sm leading-7 text-ink-600">{{ __('storefront.pages.contact.support_body') }}</p>

                        <form class="sf-contact-form mt-8 space-y-6" data-contact-form data-endpoint="{{ url('/api/v1/customer/support/tickets') }}" data-submit-label="{{ __('storefront.pages.contact.form.submit') }}" data-submitting-label="{{ __('storefront.pages.contact.form.submitting') }}" data-error-message="{{ __('storefront.pages.contact.form.error') }}">
                            <div class="grid gap-6 sm:grid-cols-2">
                                <div>
                                    <label for="contact-email" class="text-sm font-bold text-ink-700">{{ __('storefront.pages.contact.form.email') }}</label>
                                    <input id="contact-email" class="sf-field mt-2 w-full rounded-xl" type="email" name="email" dir="ltr" autocomplete="email" inputmode="email" required maxlength="255" aria-describedby="contact-email-error">
                                    <p id="contact-email-error" class="mt-2 text-sm font-semibold text-red-700" data-contact-error="email" data-error-message="{{ __('storefront.pages.contact.form.email_error') }}" hidden></p>
                                </div>
                                <div>
                                    <label for="contact-subject" class="text-sm font-bold text-ink-700">{{ __('storefront.pages.contact.form.subject') }}</label>
                                    <input id="contact-subject" class="sf-field mt-2 w-full rounded-xl" type="text" name="subject" required maxlength="255" aria-describedby="contact-subject-error">
                                    <p id="contact-subject-error" class="mt-2 text-sm font-semibold text-red-700" data-contact-error="subject" data-error-message="{{ __('storefront.pages.contact.form.required_error') }}" data-server-message="{{ __('storefront.pages.contact.form.field_error') }}" hidden></p>
                                </div>
                            </div>
                            <div>
                                <label for="contact-body" class="text-sm font-bold text-ink-700">{{ __('storefront.pages.contact.form.message') }}</label>
                                <textarea id="contact-body" class="sf-field mt-2 min-h-40 w-full resize-y rounded-xl" name="body" required maxlength="5000" aria-describedby="contact-body-error"></textarea>
                                <p id="contact-body-error" class="mt-2 text-sm font-semibold text-red-700" data-contact-error="body" data-error-message="{{ __('storefront.pages.contact.form.required_error') }}" data-server-message="{{ __('storefront.pages.contact.form.field_error') }}" hidden></p>
                            </div>
                            <div class="space-y-4">
                                <button type="submit" class="sf-button sf-button--secondary min-h-12 w-full justify-center disabled:cursor-wait disabled:opacity-65" data-contact-submit>{{ __('storefront.pages.contact.form.submit') }}</button>
                                <p class="hidden rounded-xl px-4 py-3 text-sm font-bold" role="status" aria-live="polite" data-contact-status></p>
                            </div>
                        </form>
                    </section>

                    <aside class="space-y-5">
                        @if (filled($branding['support_email']))
                            <a href="mailto:{{ $branding['support_email'] }}" class="group flex min-h-28 items-center gap-4 rounded-[1.25rem] border border-ink-200 bg-white p-5 shadow-sm transition hover:border-secondary-400 hover:bg-secondary-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary-500">
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-secondary-100 text-secondary-700" aria-hidden="true">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75 12 12.5l8.25-5.75M5.25 19.25h13.5a2 2 0 0 0 2-2V6.75a2 2 0 0 0-2-2H5.25a2 2 0 0 0-2 2v10.5a2 2 0 0 0 2 2Z" /></svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-bold text-ink-500">{{ __('storefront.pages.contact.email_label') }}</span>
                                    <bdi dir="ltr" class="mt-1 block break-all text-base font-extrabold text-ink-900">{{ $branding['support_email'] }}</bdi>
                                </span>
                            </a>
                        @endif

                        @if (filled($branding['support_phone']))
                            <a href="tel:{{ $branding['support_phone'] }}" class="group flex min-h-28 items-center gap-4 rounded-[1.25rem] border border-ink-200 bg-white p-5 shadow-sm transition hover:border-secondary-400 hover:bg-secondary-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary-500">
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-secondary-100 text-secondary-700" aria-hidden="true">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3.75H6.5A2.75 2.75 0 0 0 3.75 6.5c0 7.6 6.15 13.75 13.75 13.75a2.75 2.75 0 0 0 2.75-2.75v-1.75l-4.25-1-1.1 2.2a11.3 11.3 0 0 1-7.85-7.85L9.25 8l-1-4.25Z" /></svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-bold text-ink-500">{{ __('storefront.pages.contact.phone_label') }}</span>
                                    <bdi dir="ltr" class="mt-1 block text-base font-extrabold text-ink-900">{{ $branding['support_phone'] }}</bdi>
                                </span>
                            </a>
                        @endif

                        @if (filled($branding['address_line']))
                            <section class="flex min-h-28 items-center gap-4 rounded-[1.25rem] border border-ink-200 bg-white p-5 shadow-sm" aria-labelledby="contact-location-title">
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-accent-100 text-accent-700" aria-hidden="true">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10c0 5.25-7.5 10.25-7.5 10.25S4.5 15.25 4.5 10a7.5 7.5 0 1 1 15 0Z" /><circle cx="12" cy="10" r="2.25" /></svg>
                                </span>
                                <div>
                                    <h2 id="contact-location-title" class="text-base font-extrabold text-ink-900">{{ __('storefront.pages.contact.location_title') }}</h2>
                                    <p class="mt-1 text-sm leading-7 text-ink-600">{{ $branding['address_line'] }}</p>
                                </div>
                            </section>
                        @endif

                        <section class="rounded-[1.25rem] bg-secondary-900 p-6 text-white shadow-sm" aria-labelledby="contact-faq-title">
                            <h2 id="contact-faq-title" class="text-xl font-extrabold">{{ __('storefront.pages.contact.faq_title') }}</h2>
                            <p class="mt-2 text-sm leading-7 text-white/70">{{ __('storefront.pages.contact.faq_body') }}</p>
                            <a href="{{ url('/'.app()->getLocale().'/p/faq') }}" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-full bg-white px-5 text-sm font-extrabold text-secondary-900 transition hover:bg-secondary-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                                {{ __('storefront.pages.contact.faq_cta') }}
                            </a>
                        </section>

                        @if ($contactConfigurationPending || ! $hasDirectContact)
                            <div class="flex gap-3 rounded-[1.25rem] border border-secondary-100 bg-secondary-50 p-5 text-secondary-900" role="note">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-white font-extrabold text-secondary-700" aria-hidden="true">i</span>
                                <div>
                                    <h3 class="font-extrabold">{{ __('storefront.pages.contact.pending_title') }}</h3>
                                    <p class="mt-1 text-sm leading-7 text-secondary-700">{{ __('storefront.common.contact_configuration_pending') }}</p>
                                </div>
                            </div>
                        @endif
                    </aside>
                </div>

                <section class="mx-auto mt-10 flex max-w-5xl flex-col gap-5 rounded-[1.25rem] border border-secondary-200 bg-secondary-50 p-6 sm:flex-row sm:items-center sm:justify-between lg:mt-12 lg:p-8" aria-labelledby="contact-seller-title">
                    <div class="max-w-2xl">
                        <p class="text-sm font-bold text-secondary-700">{{ __('storefront.pages.contact.seller_eyebrow') }}</p>
                        <h2 id="contact-seller-title" class="mt-1 text-2xl font-extrabold text-ink-900">{{ __('storefront.pages.contact.seller_title') }}</h2>
                        <p class="mt-2 text-sm leading-7 text-ink-600"><x-storefront.directional-text :text="$sellerCopy" /></p>
                    </div>
                    <a href="{{ route('filament.vendor.auth.register', ['lang' => app()->getLocale()]) }}" class="sf-button sf-button--secondary w-full shrink-0 justify-center sm:w-auto sm:min-w-44">
                        {{ __('storefront.pages.contact.seller_cta') }}
                    </a>
                </section>
            </main>
        </div>
    @elseif ($cmsPage)
        @include('storefront.pages.partials.informational')
    @else
        <div class="sf-cms-page bg-[var(--sf-ivory)]">
            <section class="bg-secondary-900 text-white">
                <div class="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
                    <p class="text-sm font-bold uppercase tracking-[0.2em] text-secondary-200">{{ __('storefront.common.site_name') }}</p>
                    <h1 class="mt-4 text-4xl font-extrabold tracking-[-0.045em] sm:text-6xl">{{ $page['title'] }}</h1>
                    @if (filled($page['intro'] ?? null))
                        <p class="mt-5 max-w-2xl text-base leading-8 text-white/70">{{ $page['intro'] }}</p>
                    @endif
                </div>
            </section>
            <article class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
                <div class="rounded-[1.5rem] border border-ink-200 bg-white p-6 sm:p-10">
                    @if ($cmsPage && $body !== '')
                        <div class="space-y-5">
                            @foreach ($bodyBlocks as $index => $block)
                                @if (in_array($slug, ['terms', 'privacy', 'contact'], true) && $index % 2 === 0)
                                    <h2 class="text-lg font-extrabold text-ink-900">
                                        <x-storefront.directional-text :text="$block" />
                                    </h2>
                                @else
                                    <p class="text-sm leading-8 text-ink-600">
                                        <x-storefront.directional-text :text="$block" />
                                    </p>
                                @endif
                            @endforeach
                        </div>
                        @if ($contactConfigurationPending)
                            <p class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-semibold leading-7 text-amber-900" role="note">
                                {{ __('storefront.common.contact_configuration_pending') }}
                            </p>
                        @endif
                    @elseif (! $cmsPage)
                        <div class="space-y-8">
                            @foreach ($page['sections'] as $section)
                                <section>
                                    <h2 class="text-xl font-extrabold text-ink-900">{{ $section['title'] }}</h2>
                                    <p class="mt-3 text-sm leading-8 text-ink-600">{{ $section['body'] }}</p>
                                </section>
                            @endforeach
                        </div>
                    @else
                        <div class="grid min-h-48 place-items-center text-center">
                            <div class="max-w-xl">
                                <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-secondary-50 text-secondary-500" aria-hidden="true">
                                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75v10.5m5.25-5.25H6.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </span>
                                <h2 class="mt-4 text-xl font-extrabold text-ink-900">{{ __('storefront.pages.content_pending.title') }}</h2>
                                <p class="mt-3 text-sm leading-7 text-ink-600">{{ __('storefront.pages.content_pending.body') }}</p>
                                @if ($slug !== 'contact')
                                    <a href="{{ url('/'.app()->getLocale().'/p/contact') }}" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-full bg-secondary-900 px-5 text-sm font-bold text-white transition hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary-500">
                                        {{ __('storefront.pages.content_pending.contact') }}
                                    </a>
                                @else
                                    <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-semibold leading-7 text-amber-900" role="status">
                                        {{ __('storefront.common.contact_configuration_pending') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </article>
        </div>
    @endif
@endsection

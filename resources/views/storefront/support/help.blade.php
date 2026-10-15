@extends('storefront.layouts.app')

@php
    $locale = app()->getLocale();
@endphp

@section('title', __('storefront.pages.faq.title').' · '.__('storefront.common.site_name'))
@section('meta_description', __('storefront.pages.faq.intro'))

@section('content')
    <div class="sf-cms-page sf-faq-page" data-faq-page>
        <section class="sf-faq-hero">
            <div class="sf-faq-hero__inner sf-faq-container">
                <div class="sf-faq-hero__copy">
                    <p class="sf-faq-eyebrow">{{ __('storefront.common.site_name') }}</p>
                    <h1>{{ __('storefront.pages.faq.title') }}</h1>
                    <p class="sf-faq-hero__description">{{ __('storefront.pages.faq.intro') }}</p>
                </div>
                <form class="sf-faq-search" role="search" data-faq-search-form>
                    <label for="help-faq-search">{{ __('storefront.pages.faq.search_label') }}</label>
                    <div class="sf-faq-search__control">
                        <input id="help-faq-search" name="q" type="search" autocomplete="off" placeholder="{{ __('storefront.pages.faq.search_placeholder') }}" data-faq-search-input>
                        <button type="button" class="sf-faq-search__clear" aria-label="{{ __('storefront.pages.faq.clear_search') }}" data-faq-search-clear hidden>×</button>
                    </div>
                    <p class="sf-faq-search__status" data-faq-search-status aria-live="polite"></p>
                </form>
            </div>
        </section>

        <main class="sf-faq-content sf-faq-container" aria-labelledby="help-faq-list-title">
            <div class="sf-faq-layout">
                <div class="sf-faq-main">
                    <div class="sf-faq-section-heading">
                        <div>
                            <p class="sf-faq-section-eyebrow">{{ __('storefront.pages.faq.section_eyebrow') }}</p>
                            <h2 id="help-faq-list-title">{{ __('storefront.pages.faq.list_title') }}</h2>
                        </div>
                        <span class="sf-faq-count" data-faq-count data-faq-count-template="{{ __('storefront.pages.faq.results_count') }}"></span>
                    </div>

                    <div class="sf-faq-list" data-faq-list>
                        @foreach ($categories as $category)
                            @if ($category->items->isNotEmpty())
                                <section class="mb-8" aria-labelledby="help-faq-category-{{ $category->public_id }}">
                                    <h3 id="help-faq-category-{{ $category->public_id }}" class="mb-3 text-lg font-extrabold text-ink-900">{{ $category->getTranslation('name', $locale, useFallbackLocale: true) }}</h3>
                                    @foreach ($category->items as $item)
                                        @php
                                            $question = $item->getTranslation('question', $locale, useFallbackLocale: true);
                                            $answer = $item->getTranslation('answer', $locale, useFallbackLocale: true);
                                        @endphp
                                        <details class="sf-faq-item" data-faq-item data-faq-search="{{ $question.' '.$answer }}">
                                            <summary>
                                                <span class="sf-faq-item__question">{{ $question }}</span>
                                                <span class="sf-faq-item__toggle" aria-hidden="true">+</span>
                                            </summary>
                                            <div class="sf-faq-item__answer"><p>{{ $answer }}</p></div>
                                        </details>
                                    @endforeach
                                </section>
                            @endif
                        @endforeach
                    </div>

                    <div class="sf-faq-empty" data-faq-empty hidden>
                        <span class="sf-faq-empty__mark" aria-hidden="true">?</span>
                        <h2>{{ __('storefront.pages.faq.empty_title') }}</h2>
                        <p>{{ __('storefront.pages.faq.empty_body') }}</p>
                    </div>

                    <section class="sf-faq-cta" aria-labelledby="help-faq-support-title">
                        <div>
                            <h2 id="help-faq-support-title">{{ __('storefront.pages.faq.support_title') }}</h2>
                            <p>{{ __('storefront.pages.faq.support_body') }}</p>
                        </div>
                        <a href="#support-ticket" class="sf-faq-cta__link">{{ __('support::support.help.contact_support') }} <span aria-hidden="true">→</span></a>
                    </section>

                    @if (session('supportTicketReference'))
                        <p class="mt-6 rounded-xl border border-green-700/20 bg-green-50 p-4 text-sm font-semibold text-green-900" role="status" data-support-ticket-success>
                            {{ __('support::support.help.ticket_received', ['reference' => session('supportTicketReference')]) }}
                        </p>
                    @endif

                    <section id="support-ticket" class="mt-8 rounded-2xl border border-ink-200 bg-white p-6" aria-labelledby="support-ticket-title">
                        <h2 id="support-ticket-title" class="text-xl font-extrabold text-ink-900">{{ __('support::support.help.ticket_title') }}</h2>
                        <p class="mt-2 text-sm leading-6 text-ink-600">{{ __('support::support.help.ticket_intro') }}</p>
                        <form class="mt-5 grid gap-4" method="POST" action="{{ route('storefront.support.tickets.store', ['locale' => $locale]) }}" data-support-ticket-form>
                            @csrf
                            <div>
                                <label class="mb-1 block text-sm font-semibold" for="support-ticket-subject">{{ __('support::support.help.ticket_subject') }}</label>
                                <input class="w-full rounded-xl border border-ink-300 px-3 py-2" id="support-ticket-subject" name="subject" value="{{ old('subject') }}" required maxlength="255">
                                @error('subject')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-semibold" for="support-ticket-email">{{ __('support::support.help.ticket_email') }}</label>
                                <input class="w-full rounded-xl border border-ink-300 px-3 py-2" id="support-ticket-email" name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" @if (auth()->guest()) required @endif maxlength="255">
                                @error('email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-semibold" for="support-ticket-body">{{ __('support::support.help.ticket_body') }}</label>
                                <textarea class="w-full rounded-xl border border-ink-300 px-3 py-2" id="support-ticket-body" name="body" rows="5" required maxlength="5000">{{ old('body') }}</textarea>
                                @error('body')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <button class="sf-button sf-button--primary justify-self-start" type="submit">{{ __('support::support.help.ticket_submit') }}</button>
                        </form>
                    </section>
                </div>
            </div>
        </main>
    </div>
@endsection

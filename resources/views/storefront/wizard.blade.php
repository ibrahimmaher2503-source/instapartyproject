@extends('storefront.layouts.app')

@php
    $plannerSteps = [
        __('storefront.wizard.steps.occasion'),
        __('storefront.wizard.steps.location'),
        __('storefront.wizard.steps.details'),
    ];
    $storefrontText = app(App\Modules\Shared\Application\Services\StorefrontText::class);
    $bookingMode = request()->filled('service');
    $isRtl = app()->getLocale() === 'ar';
    $nextIcon = $isRtl ? '←' : '→';
    $backIcon = $isRtl ? '→' : '←';
@endphp

@section('title', __('storefront.wizard.title').' · '.__('storefront.common.site_name'))

@section('content')
    <div class="sf-wizard-page">
        <div
            class="sf-wizard-container"
            data-storefront-planner
            data-booking-mode="{{ $bookingMode ? 'true' : 'false' }}"
            data-initial-step="{{ $errors->has('city_public_id') || $errors->has('governorate_public_id') ? 1 : ($errors->any() ? 2 : 0) }}"
            data-initial-governorate="{{ old('governorate_public_id', request('governorate_public_id')) }}"
            data-initial-city="{{ old('city_public_id', request('city_public_id')) }}"
            data-error-occasion="{{ __('storefront.wizard.validation.occasion_required') }}"
            data-error-times-required="{{ __('storefront.wizard.validation.times_required') }}"
            data-error-times-invalid="{{ __('storefront.wizard.validation.times_invalid') }}"
            data-error-location="{{ __('storefront.wizard.validation.location_required') }}"
            data-error-location-load="{{ __('storefront.wizard.step2.load_error') }}"
        >
            <header class="sf-wizard-intro">
                <p class="sf-wizard-eyebrow">{{ __('storefront.wizard.intro_eyebrow') }}</p>
                <h1 class="sf-wizard-title">{{ __('storefront.wizard.title') }}</h1>
                <p class="sf-wizard-subtitle">{{ __('storefront.wizard.subtitle') }}</p>
            </header>

            <ol class="sf-wizard-stepper" aria-label="{{ __('storefront.wizard.title') }}">
                @foreach ($plannerSteps as $index => $step)
                    <li class="sf-wizard-step" data-planner-step-indicator="{{ $index }}">
                        <div class="sf-wizard-step__content">
                            <span class="sf-wizard-step__mark" data-planner-step-mark>
                                <span data-planner-step-number>{{ $index + 1 }}</span>
                                <span class="hidden" aria-hidden="true" data-planner-step-check>✓</span>
                            </span>
                            <span class="sf-wizard-step__label">{{ $step }}</span>
                        </div>
                        @if ($index < array_key_last($plannerSteps))
                            <span class="sf-wizard-step__connector" aria-hidden="true" data-planner-step-connector="{{ $index }}"></span>
                        @endif
                    </li>
                @endforeach
            </ol>

            <div class="sf-wizard-card">
                <form
                    class="sf-wizard-form"
                    method="{{ $bookingMode ? 'POST' : 'GET' }}"
                    action="{{ $bookingMode ? route('storefront.cart.setup') : route('storefront.search') }}"
                    data-planner-form
                >
                    @if ($bookingMode)
                        @csrf
                        <input type="hidden" name="service_id" value="{{ request('service') }}">
                        <input type="hidden" name="timezone" value="Africa/Cairo" data-browser-timezone>
                    @endif
                    <input type="hidden" name="occasion" value="{{ old('occasion', request('occasion')) }}" data-planner-occasion>
                    <input type="hidden" name="city_public_id" value="{{ old('city_public_id', request('city_public_id')) }}" data-planner-city>
                    @unless ($bookingMode)
                        <input type="hidden" name="planner" value="1">
                    @endunless
                    @if ($bookingMode)
                        <input type="hidden" name="address[city_id]" value="{{ old('address.city_id', request('city_public_id')) }}" data-planner-booking-city>
                    @endif
                    <input type="hidden" name="vendor" value="{{ request('vendor') }}">

                    <div
                        @class(['sf-wizard-error', 'hidden' => ! $errors->any()])
                        role="alert"
                        aria-live="polite"
                        data-planner-error
                    >{{ $errors->first() }}</div>

                    <section class="sf-wizard-panel" data-planner-panel="0" aria-labelledby="planner-step-occasion">
                        <div class="sf-wizard-panel__intro">
                            <p class="sf-wizard-panel__eyebrow">{{ __('storefront.wizard.steps.occasion') }}</p>
                            <h2 id="planner-step-occasion" tabindex="-1">{{ __('storefront.wizard.step1.headline') }}</h2>
                            <p>{{ __('storefront.wizard.step1.body') }}</p>
                        </div>

                        <div class="sf-wizard-occasion-grid" role="radiogroup" aria-labelledby="planner-step-occasion">
                            @forelse ($occasions as $occasion)
                                <button
                                    type="button"
                                    role="radio"
                                    aria-checked="{{ old('occasion', request('occasion')) === $occasion->code ? 'true' : 'false' }}"
                                    class="sf-wizard-occasion-card"
                                    data-planner-occasion-option="{{ $occasion->code }}"
                                >
                                    <span class="sf-wizard-occasion-card__top">
                                        <span class="sf-wizard-occasion-card__title">{{ $storefrontText->translation($occasion, 'name') }}</span>
                                        <span class="sf-wizard-occasion-card__check" aria-hidden="true" data-planner-occasion-check>✓</span>
                                    </span>
                                    @if ($storefrontText->translation($occasion, 'description'))
                                        <span class="sf-wizard-occasion-card__description">{{ $storefrontText->translation($occasion, 'description') }}</span>
                                    @endif
                                </button>
                            @empty
                                <p class="sf-wizard-empty">{{ __('storefront.wizard.step1.no_occasions') }}</p>
                            @endforelse
                        </div>

                        <div class="sf-wizard-schedule">
                            <div class="sf-wizard-section-heading">
                                <h3>{{ __('storefront.wizard.step1.date_label') }}</h3>
                                <p>{{ __('storefront.wizard.step1.date_body') }}</p>
                            </div>
                            <div class="sf-wizard-schedule-grid">
                                <label class="sf-wizard-field-card">
                                    <span class="sf-wizard-field-label">{{ __('storefront.checkout.event.starts_at') }}</span>
                                    <span class="sf-localized-date sf-wizard-date-control" data-localized-date>
                                        <input
                                            type="datetime-local"
                                            lang="{{ app()->getLocale() }}"
                                            name="event_starts_at"
                                            value="{{ old('event_starts_at', request('event_starts_at')) }}"
                                            class="sf-field sf-wizard-field"
                                            data-localized-date-input
                                            required
                                        >
                                        <span aria-hidden="true" data-localized-date-placeholder>{{ __('storefront.common.datetime_placeholder') }}</span>
                                    </span>
                                </label>
                                <label class="sf-wizard-field-card">
                                    <span class="sf-wizard-field-label">{{ __('storefront.checkout.event.ends_at') }}</span>
                                    <span class="sf-localized-date sf-wizard-date-control" data-localized-date>
                                        <input
                                            type="datetime-local"
                                            lang="{{ app()->getLocale() }}"
                                            name="event_ends_at"
                                            value="{{ old('event_ends_at', request('event_ends_at')) }}"
                                            class="sf-field sf-wizard-field"
                                            data-localized-date-input
                                            required
                                        >
                                        <span aria-hidden="true" data-localized-date-placeholder>{{ __('storefront.common.datetime_placeholder') }}</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="sf-wizard-actions sf-wizard-actions--end">
                            <button type="button" class="sf-button sf-button--secondary sf-wizard-next" data-planner-next>
                                {{ __('storefront.wizard.nav.next') }}
                                <span class="sf-wizard-button-icon" aria-hidden="true">{{ $nextIcon }}</span>
                            </button>
                        </div>
                    </section>

                    <section class="sf-wizard-panel hidden" data-planner-panel="1" aria-labelledby="planner-step-location">
                        <div class="sf-wizard-panel__intro">
                            <p class="sf-wizard-panel__eyebrow">{{ __('storefront.wizard.steps.location') }}</p>
                            <h2 id="planner-step-location" tabindex="-1">{{ __('storefront.wizard.step2.headline') }}</h2>
                            <p>{{ __('storefront.wizard.step2.body') }}</p>
                        </div>

                        <div class="sf-wizard-location-grid">
                            <label class="sf-wizard-field-card">
                                <span class="sf-wizard-field-label">{{ __('storefront.wizard.step2.governorate_label') }}</span>
                                <select name="governorate_public_id" class="sf-field sf-wizard-field" data-planner-governorate>
                                    <option value="">{{ __('storefront.wizard.step2.select_governorate') }}</option>
                                </select>
                            </label>
                            <label class="sf-wizard-field-card">
                                <span class="sf-wizard-field-label">{{ __('storefront.wizard.step2.city_label') }}</span>
                                <select class="sf-field sf-wizard-field" data-planner-city-select>
                                    <option value="">{{ __('storefront.wizard.step2.select_city') }}</option>
                                </select>
                            </label>
                        </div>

                        <p class="sf-wizard-status" data-planner-loading aria-live="polite">{{ __('storefront.wizard.step2.loading') }}</p>

                        <div class="sf-wizard-actions">
                            <button type="button" class="sf-wizard-back" data-planner-back>
                                <span class="sf-wizard-button-icon" aria-hidden="true">{{ $backIcon }}</span>
                                {{ __('storefront.wizard.nav.back') }}
                            </button>
                            <button type="button" class="sf-button sf-button--secondary sf-wizard-next" data-planner-next>
                                {{ __('storefront.wizard.nav.next') }}
                                <span class="sf-wizard-button-icon" aria-hidden="true">{{ $nextIcon }}</span>
                            </button>
                        </div>
                    </section>

                    <section class="sf-wizard-panel hidden" data-planner-panel="2" aria-labelledby="planner-step-details">
                        <div class="sf-wizard-panel__intro">
                            <p class="sf-wizard-panel__eyebrow">{{ __('storefront.wizard.steps.details') }}</p>
                            <h2 id="planner-step-details" tabindex="-1">{{ __('storefront.wizard.step3.headline') }}</h2>
                            <p>{{ __('storefront.wizard.step3.body') }}</p>
                        </div>

                        <div class="sf-wizard-details-grid">
                            <label class="sf-wizard-field-card">
                                <span class="sf-wizard-field-label">{{ __('storefront.wizard.step3.guests_label') }}</span>
                                <input
                                    type="number"
                                    name="guest_count"
                                    min="1"
                                    value="{{ old('guest_count', request('guest_count')) }}"
                                    placeholder="{{ __('storefront.wizard.step3.guests_placeholder') }}"
                                    class="sf-field sf-wizard-field"
                                >
                            </label>
                            <label class="sf-wizard-field-card">
                                <span class="sf-wizard-field-label">{{ __('storefront.wizard.step3.celebrant_label') }}</span>
                                <input
                                    type="text"
                                    name="celebrant_name"
                                    value="{{ old('celebrant_name', request('celebrant_name')) }}"
                                    placeholder="{{ __('storefront.wizard.step3.celebrant_placeholder') }}"
                                    class="sf-field sf-wizard-field"
                                >
                            </label>
                        </div>

                        @if ($bookingMode)
                            <div class="sf-wizard-address">
                                <div class="sf-wizard-section-heading">
                                    <h3>{{ __('storefront.checkout.event.section_address') }}</h3>
                                </div>
                                <div class="sf-wizard-address-grid">
                                    <label class="sf-wizard-field-card sf-wizard-field-card--wide">
                                        <span class="sf-wizard-field-label">{{ __('storefront.checkout.event.address_line') }}</span>
                                        <input type="text" name="address[address_line]" value="{{ old('address.address_line') }}" class="sf-field sf-wizard-field" required>
                                    </label>
                                    <label class="sf-wizard-field-card">
                                        <span class="sf-wizard-field-label">{{ __('storefront.checkout.event.recipient_name') }}</span>
                                        <input type="text" name="address[recipient_name]" value="{{ old('address.recipient_name') }}" class="sf-field sf-wizard-field" required>
                                    </label>
                                    <label class="sf-wizard-field-card">
                                        <span class="sf-wizard-field-label">{{ __('storefront.checkout.event.recipient_phone') }}</span>
                                        <input type="tel" dir="ltr" inputmode="tel" name="address[recipient_phone_e164]" value="{{ old('address.recipient_phone_e164') }}" placeholder="+201..." class="sf-field sf-wizard-field text-left" required>
                                    </label>
                                    <label class="sf-wizard-field-card">
                                        <span class="sf-wizard-field-label">{{ __('storefront.checkout.event.building') }}</span>
                                        <input type="text" name="address[building]" value="{{ old('address.building') }}" class="sf-field sf-wizard-field">
                                    </label>
                                    <label class="sf-wizard-field-card">
                                        <span class="sf-wizard-field-label">{{ __('storefront.checkout.event.landmark') }}</span>
                                        <input type="text" name="address[landmark]" value="{{ old('address.landmark') }}" class="sf-field sf-wizard-field">
                                    </label>
                                </div>
                            </div>
                        @endif

                        <div class="sf-wizard-actions">
                            <button type="button" class="sf-wizard-back" data-planner-back>
                                <span class="sf-wizard-button-icon" aria-hidden="true">{{ $backIcon }}</span>
                                {{ __('storefront.wizard.nav.back') }}
                            </button>
                            <button type="submit" class="sf-button sf-button--secondary sf-wizard-next">
                                {{ $bookingMode ? __('storefront.checkout.event.submit') : __('storefront.wizard.nav.start_exploring') }}
                                <span class="sf-wizard-button-icon" aria-hidden="true">{{ $nextIcon }}</span>
                            </button>
                        </div>
                    </section>
                </form>
            </div>
        </div>
    </div>
@endsection

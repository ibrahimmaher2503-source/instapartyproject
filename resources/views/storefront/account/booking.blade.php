@extends('storefront.account.layout')
@section('account-title', __('account.booking_details'))
@section('account-content')
@php($viewerTimezone = auth()->user()?->timezone ?? 'Africa/Cairo')
@php
    $lifecycle = $booking->lifecycle_status->getValue();
    $hasItems = $booking->vendors->contains(fn ($vendor) => $vendor->items->isNotEmpty());
    $hasActivePayment = $booking->payments->contains(fn ($payment) => in_array($payment->status->getValue(), ['pending', 'authorized', 'captured'], true));
    $canCancel = ! in_array($lifecycle, ['active', 'completed', 'cancelled'], true)
        && ! $booking->vendors->contains(fn ($vendor) => in_array($vendor->sub_status->value, ['in_progress', 'completed'], true));
    $canPay = $lifecycle === 'confirmed' && $booking->payment_status->getValue() === 'unpaid'
        && $hasItems && ! $hasActivePayment;
    $paymentAwaitingConfirmation = in_array($lifecycle, ['submitted', 'vendor_review', 'customer_review'], true)
        && $booking->payment_status->getValue() === 'unpaid' && $hasItems && ! $hasActivePayment;
@endphp
<a class="sf-text-link" href="{{ route('storefront.account.bookings') }}">{{ __('account.back_bookings') }}</a>
@if (session('booking_submitted') === $booking->public_id)
<section class="sf-account-panel" data-booking-confirmation role="status"><h2>{{ __('account.booking_submitted') }}</h2><p>{{ __('booking.columns.reference_no') }}: <bdi>{{ $booking->reference_no }}</bdi></p><a class="sf-text-link" href="{{ route('storefront.account.bookings.show', $booking->public_id) }}">{{ __('account.view_details') }}</a></section>
@endif
<section class="sf-account-panel"><h2><bdi>{{ $booking->reference_no }}</bdi></h2><span class="sf-account-status">{{ __('booking.lifecycle_status.'.$booking->lifecycle_status->getValue()) }}</span><dl class="sf-account-details"><div><dt>{{ __('booking.display_timezone', ['field' => __('account.date'), 'timezone' => $viewerTimezone]) }}</dt><dd>{{ $booking->event_starts_at?->copy()->timezone($viewerTimezone)->translatedFormat(app(App\Modules\Shared\Application\Services\StorefrontText::class)->dateTimeFormat()) ?? '—' }}</dd></div><div><dt>{{ __('account.occasion') }}</dt><dd>{{ $booking->occasion?->getTranslation('name', app()->getLocale()) ?? '—' }}</dd></div><div><dt>{{ __('account.booking_total') }}</dt><dd>{{ $booking->total->formatTo(app()->getLocale() === 'ar' ? 'ar_EG' : 'en_GB') }}</dd></div>
    @if ($cancellation)
        <div><dt>{{ __('account.amount_captured') }}</dt><dd>{{ \Brick\Money\Money::ofMinor($cancellation['payment_totals']['captured_minor'], $booking->total_currency)->formatTo(app()->getLocale() === 'ar' ? 'ar_EG' : 'en_GB') }}</dd></div>
        <div><dt>{{ __('account.amount_refunded') }}</dt><dd>{{ \Brick\Money\Money::ofMinor($cancellation['payment_totals']['refunded_minor'], $booking->total_currency)->formatTo(app()->getLocale() === 'ar' ? 'ar_EG' : 'en_GB') }}</dd></div>
        <div><dt>{{ __('account.net_paid_after_refunds') }}</dt><dd>{{ \Brick\Money\Money::ofMinor($cancellation['payment_totals']['net_paid_minor'], $booking->total_currency)->formatTo(app()->getLocale() === 'ar' ? 'ar_EG' : 'en_GB') }}</dd></div>
    @endif
</dl></section>
@if ($cancellation)
<section class="sf-account-panel" data-cancellation-details>
    <h2>{{ __('account.cancellation_details') }}</h2>
    @if (filled($cancellation['reason']))
        <p><strong>{{ __('account.cancellation_reason') }}:</strong> <span class="whitespace-pre-wrap break-words">{{ $cancellation['reason'] }}</span></p>
    @else
        <p>{{ __('account.cancellation_reason_help') }}</p>
    @endif
    <p role="status" data-refund-guidance>{{ __('account.refund_guidance.'.$cancellation['refund_guidance']) }}</p>
</section>
@endif
<section class="sf-account-panel" data-event-details><h2>{{ __('booking.sections.event') }}</h2><dl class="sf-account-details">
    <div><dt>{{ __('booking.display_timezone', ['field' => __('booking.columns.event_ends_at'), 'timezone' => $viewerTimezone]) }}</dt><dd>{{ $booking->event_ends_at?->copy()->timezone($viewerTimezone)->translatedFormat(app(App\Modules\Shared\Application\Services\StorefrontText::class)->dateTimeFormat()) ?? __('account.not_provided') }}</dd></div>
    <div><dt>{{ __('booking.columns.guest_count') }}</dt><dd>{{ $booking->guest_count ?? __('account.not_provided') }}</dd></div>
</dl></section>
<section class="sf-account-panel" data-delivery-details><h2>{{ __('booking.wizard.step_address') }}</h2><dl class="sf-account-details">
    <div><dt>{{ __('booking.columns.city') }}</dt><dd>{{ $booking->address?->city?->getTranslation('name', app()->getLocale()) ?: __('account.not_provided') }}</dd></div>
    @foreach (['address_line', 'building', 'floor', 'apartment', 'landmark', 'recipient_name'] as $field)
        <div><dt>{{ __('booking.columns.'.$field) }}</dt><dd class="whitespace-pre-wrap break-words">{{ filled($booking->address?->{$field}) ? $booking->address->{$field} : __('account.not_provided') }}</dd></div>
    @endforeach
    <div><dt>{{ __('booking.columns.recipient_phone') }}</dt><dd><bdi dir="ltr">{{ $booking->address?->recipient_phone_e164 ?: __('account.not_provided') }}</bdi></dd></div>
</dl></section>
@if ($booking->customerNotes->isNotEmpty())
<section class="sf-account-panel" data-event-notes><h2>{{ __('storefront.wizard.step3.notes_label') }}</h2><p class="whitespace-pre-wrap break-words">{{ $booking->customerNotes->first()->body }}</p></section>
@endif
<section class="sf-account-panel" data-booking-actions>
    <h2>{{ __('account.booking_actions') }}</h2>
    <dl class="sf-account-details"><div><dt>{{ __('account.delivery_total') }}</dt><dd>{{ $booking->delivery_total->formatTo(app()->getLocale() === 'ar' ? 'ar_EG' : 'en_GB') }}</dd></div></dl>
    @if ($canCancel)
        <form method="POST" action="{{ url('/api/v1/customer/bookings/'.$booking->public_id.'/cancel') }}" data-booking-api-form data-preview="{{ url('/api/v1/customer/bookings/'.$booking->public_id.'/cancellation-preview') }}" data-confirm="{{ __('account.cancel_confirm') }}" data-blocked="{{ __('account.cancellation_unavailable') }}" data-manual-review="{{ __('account.cancel_manual_review') }}" data-error="{{ __('account.action_error') }}">
            <button class="sf-button sf-button--quiet" type="submit">{{ __('account.cancel_booking') }}</button>
            <p data-booking-feedback role="status" aria-live="polite" class="whitespace-pre-wrap"></p>
        </form>
    @else
        <p>{{ __('account.cancellation_unavailable') }}</p>
    @endif
    @if ($canPay)
        <form method="POST" action="{{ route('payments.initiate', $booking->public_id) }}" data-booking-api-form data-payment data-error="{{ __('account.action_error') }}">
            <input type="hidden" name="method" value="card">
            <button class="sf-button sf-button--primary" type="submit">{{ __('account.pay_booking') }}</button>
            <p data-booking-feedback role="status" aria-live="polite"></p>
        </form>
    @else
        <div data-payment-continuation>
            <p>{{ $paymentAwaitingConfirmation ? __('account.payment_waiting_for_confirmation') : __('account.payment_unavailable') }}</p>
            <a class="sf-text-link" href="{{ route('storefront.account.bookings') }}">{{ __('account.view_booking_status') }}</a>
        </div>
    @endif
    @unless (in_array(true, $reviewableItems, true))
        <p>{{ __('account.review_unavailable') }}</p>
    @endunless
</section>
@if ($refunds->isNotEmpty())
<section class="sf-account-panel" data-refunds><h2>{{ __('refunds.models.refund.plural') }}</h2>@foreach ($refunds as $refund)<article class="sf-account-row"><span class="sf-account-status">{{ __('refunds.status.'.$refund->status->value) }}</span><p>{{ \Brick\Money\Money::ofMinor($refund->amountMinor, $refund->currency)->formatTo(app()->getLocale() === 'ar' ? 'ar_EG' : 'en_GB') }}</p></article>@endforeach</section>
@endif
<section class="sf-account-panel"><h2>{{ __('account.services') }}</h2>
    @if($lifecycle === 'cancelled')<p>{{ __('account.cancelled_booking_item_history') }}</p>@endif
    @foreach($booking->vendors as $vendor)
        <div class="sf-account-vendor"><h3>{{ $vendor->vendor?->getTranslation('business_name', app()->getLocale()) ?? __('account.vendor') }}</h3>
            @foreach($vendor->items as $item)
                <div class="sf-account-row"><div><h3>{{ $item->name_snapshot[app()->getLocale()] ?? $item->name_snapshot['en'] ?? __('account.service') }}</h3><p>{{ __('booking.product_type.'.$item->product_type->value) }} · {{ $item->quantity }} · {{ $item->line_total->formatTo(app()->getLocale() === 'ar' ? 'ar_EG' : 'en_GB') }}</p><p>{{ __('booking.item_status.'.$item->item_status) }}</p></div>
                    @if($availableServices[$item->public_id] ?? null)
                        <a class="sf-text-link" href="{{ url('/'.app()->getLocale().'/services/'.$availableServices[$item->public_id]->publicId) }}">{{ __('account.view_service') }}</a>
                    @else
                        <p>{{ __('account.service_unavailable') }}</p>
                    @endif
                </div>
                @if ($reviewableItems[$item->public_id] ?? false)
                    <form method="POST" action="{{ route('reviews.service.store', $item->public_id) }}" data-booking-api-form data-error="{{ __('account.action_error') }}">
                        <label for="rating-{{ $item->public_id }}">{{ __('account.rating') }}</label>
                        <select name="rating" id="rating-{{ $item->public_id }}" required>@foreach(range(5, 1) as $rating)<option value="{{ $rating }}">{{ $rating }}</option>@endforeach</select>
                        <label for="review-{{ $item->public_id }}">{{ __('account.review_body') }}</label>
                        <textarea name="body" id="review-{{ $item->public_id }}" maxlength="2000" rows="3"></textarea>
                        <button class="sf-button sf-button--quiet" type="submit">{{ __('account.write_review') }}</button>
                        <p data-booking-feedback role="status" aria-live="polite"></p>
                    </form>
                @endif
            @endforeach
        </div>
    @endforeach
</section>
@endsection

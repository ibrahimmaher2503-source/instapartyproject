@extends('storefront.account.layout')
@section('account-title', __('account.payments'))
@section('account-content')
@forelse($payments as $payment)<article class="sf-account-panel sf-account-row"><div><span class="sf-account-status">{{ __('payments.status.'.$payment->status->getValue()) }}</span><h2><bdi>{{ $payment->public_id }}</bdi></h2><p>{{ $payment->created_at?->timezone(auth()->user()->timezone ?? 'Africa/Cairo')->translatedFormat('d F Y، H:i') }} · {{ $payment->amount->formatTo(app()->getLocale() === 'ar' ? 'ar_EG' : 'en_GB') }}</p></div>@if($payment->booking)<a class="sf-text-link" href="{{ route('storefront.account.bookings.show', $payment->booking->public_id) }}">{{ $payment->booking->reference_no }}</a>@endif</article>@empty<div class="sf-account-empty">{{ __('account.no_payments') }}</div>@endforelse
{{ $payments->links() }}
@if ($refunds->isNotEmpty())
<section class="sf-account-panel" data-refunds>
    <h2>{{ __('refunds.models.refund.plural') }}</h2>
    @foreach ($refunds as $refundRow)
        @php($refund = $refundRow['refund'])
        <article class="sf-account-row">
            <div>
                <span class="sf-account-status">{{ __('refunds.status.'.$refund->status->value) }}</span>
                <p>{{ \Brick\Money\Money::ofMinor($refund->amountMinor, $refund->currency)->formatTo(app()->getLocale() === 'ar' ? 'ar_EG' : 'en_GB') }}</p>
            </div>
            <a class="sf-text-link" href="{{ route('storefront.account.bookings.show', $refundRow['booking_public_id']) }}">{{ $refundRow['booking_reference'] }}</a>
        </article>
    @endforeach
</section>
@endif
@endsection

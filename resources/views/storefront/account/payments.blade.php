@extends('storefront.account.layout')
@section('account-title', __('account.payments'))
@section('account-content')
@forelse($payments as $payment)<article class="sf-account-panel sf-account-row"><div><span class="sf-account-status">{{ __('payments.status.'.$payment->status->getValue()) }}</span><h2><bdi>{{ $payment->public_id }}</bdi></h2><p>{{ $payment->created_at?->timezone(auth()->user()->timezone ?? 'Africa/Cairo')->translatedFormat('d F Y، H:i') }} · {{ $payment->amount->formatTo(app()->getLocale() === 'ar' ? 'ar_EG' : 'en_GB') }}</p></div>@if($payment->booking)<a class="sf-text-link" href="{{ route('storefront.account.bookings.show', $payment->booking->public_id) }}">{{ $payment->booking->reference_no }}</a>@endif</article>@empty<div class="sf-account-empty">{{ __('account.no_payments') }}</div>@endforelse
{{ $payments->links() }}
@endsection

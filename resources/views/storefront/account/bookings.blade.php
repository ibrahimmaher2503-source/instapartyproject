@extends('storefront.account.layout')
@section('account-title', __('account.bookings'))
@section('account-content')
@forelse($bookings as $booking)<article class="sf-account-panel sf-account-row"><div><span class="sf-account-status">{{ __('booking.lifecycle_status.'.$booking->lifecycle_status->getValue()) }}</span><h2><bdi>{{ $booking->reference_no }}</bdi></h2><p>{{ $booking->event_starts_at?->timezone(auth()->user()->timezone ?? 'Africa/Cairo')->translatedFormat(app(App\Modules\Shared\Application\Services\StorefrontText::class)->dateTimeFormat()) ?? __('account.date_not_set') }} <bdi>{{ auth()->user()->timezone ?? 'Africa/Cairo' }}</bdi></p></div><a class="sf-button sf-button--primary" href="{{ route('storefront.account.bookings.show', $booking->public_id) }}">{{ __('account.view_details') }}</a></article>@empty<div class="sf-account-empty"><p>{{ __('account.no_bookings') }}</p><a class="sf-button sf-button--primary" href="{{ url('/'.app()->getLocale().'/search') }}">{{ __('account.browse_services') }}</a></div>@endforelse
{{ $bookings->links() }}
@endsection

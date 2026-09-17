@extends('storefront.account.layout')
@section('account-title', __('account.hello', ['name' => $user->name]))
@section('account-content')
<div class="sf-account-stats"><div><span>{{ __('account.active_bookings') }}</span><strong>{{ $activeBookings }}</strong></div><div><span>{{ __('account.upcoming_bookings') }}</span><strong>{{ $upcomingCount }}</strong></div><div><span>{{ __('account.unread') }}</span><strong>{{ $unreadCount }}</strong></div></div>
<section class="sf-account-panel"><h2>{{ __('account.next_booking') }}</h2>@if($upcoming)<div class="sf-account-row"><div><h3>{{ $upcoming->reference_no }}</h3><p>{{ $upcoming->event_starts_at?->timezone(auth()->user()->timezone ?? 'Africa/Cairo')->translatedFormat('d F Y، H:i') }}</p></div><a class="sf-button sf-button--primary" href="{{ route('storefront.account.bookings.show', $upcoming->public_id) }}">{{ __('account.view_details') }}</a></div>@else<div class="sf-account-empty"><p>{{ __('account.no_upcoming') }}</p><a class="sf-button sf-button--primary" href="{{ url('/'.app()->getLocale().'/search') }}">{{ __('account.browse_services') }}</a></div>@endif</section>
@endsection

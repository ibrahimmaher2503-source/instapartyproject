@extends('storefront.layouts.app')
@section('title')@yield('account-title') · {{ __('storefront.common.site_name') }}@endsection
@php
    $sections = ['dashboard', 'bookings', 'payments', 'favorites', 'notifications', 'reviews', 'profile', 'security'];
@endphp
@section('content')
<div class="sf-account"><div class="sf-account-container">
    <header class="sf-account-intro"><p>{{ __('account.title') }}</p><h1>@yield('account-title')</h1></header>
    <div class="sf-account-grid">
        <aside class="sf-account-nav"><nav aria-label="{{ __('account.navigation') }}">
            @foreach($sections as $section)<a href="{{ route('storefront.account.'.$section) }}" @if(request()->routeIs('storefront.account.'.$section.'*')) aria-current="page" @endif>{{ __('account.'.$section) }}</a>@endforeach
            <a href="{{ route('storefront.account.addresses') }}" @if(request()->routeIs('storefront.account.addresses*')) aria-current="page" @endif>{{ __('account.addresses') }}</a>
            <a href="{{ route('storefront.account.saved-searches.index') }}" @if(request()->routeIs('storefront.account.saved-searches.*')) aria-current="page" @endif>{{ __('account.saved_searches') }}</a>
            <a href="{{ route('storefront.account.loyalty') }}" @if(request()->routeIs('storefront.account.loyalty')) aria-current="page" @endif>{{ __('account.loyalty') }}</a>
            <a href="{{ route('storefront.account.chats') }}" @if(request()->routeIs('storefront.account.chats')) aria-current="page" @endif>{{ __('account.chats') }}</a>
            <a href="{{ route('storefront.account.inquiries.index') }}" @if(request()->routeIs('storefront.account.inquiries*', 'storefront.inquiries.show')) aria-current="page" @endif>{{ __('communication::communication.vendor_inquiries.my_inquiries') }}</a>
            <a href="{{ url('/'.app()->getLocale().'/p/faq') }}">{{ __('account.support') }}</a>
            <form method="POST" action="{{ route('storefront.auth.logout') }}">@csrf<button type="submit">{{ __('storefront.nav.logout') }}</button></form>
        </nav></aside>
        <section class="sf-account-content">
            @if(session('status'))<div class="sf-account-alert" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="sf-account-alert sf-account-alert--error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('account-content')
        </section>
    </div>
</div></div>
@endsection

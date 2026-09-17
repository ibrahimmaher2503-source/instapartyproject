@extends('storefront.layouts.app')
@php
    $sections = ['dashboard', 'bookings', 'payments', 'favorites', 'notifications', 'reviews', 'profile', 'security'];
@endphp
@section('content')
<div class="sf-account"><div class="sf-account-container">
    <header class="sf-account-intro"><p>{{ __('account.title') }}</p><h1>@yield('account-title')</h1></header>
    <div class="sf-account-grid">
        <aside class="sf-account-nav"><nav aria-label="{{ __('account.navigation') }}">
            @foreach($sections as $section)<a href="{{ route('storefront.account.'.$section) }}" @if(request()->routeIs('storefront.account.'.$section.'*')) aria-current="page" @endif>{{ __('account.'.$section) }}</a>@endforeach
            <a href="{{ route('storefront.wizard') }}">{{ __('account.plan_cta') }}</a>
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

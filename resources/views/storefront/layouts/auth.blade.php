@php
    $locale = app()->getLocale();
    $direction = $locale === 'ar' ? 'rtl' : 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('storefront.common.site_name'))</title>
    @foreach ($storefrontLocaleUrls ?? [] as $alternate => $alternateUrl)
        <link rel="alternate" hreflang="{{ $alternate }}" href="{{ $alternateUrl }}">
    @endforeach
    @vite(['resources/css/storefront.css', 'resources/js/storefront.js'])
    @include('storefront.partials.theme')
</head>
<body class="sf-storefront sf-auth-body min-h-screen">
    @include('storefront.partials.auth-header')
    <main id="main" class="sf-auth-page">
        <div class="sf-auth-shell">
            <aside class="sf-auth-art" aria-hidden="true">
                <div class="sf-auth-art__wash"></div>
                <div class="sf-auth-art__copy">
                    <p class="sf-auth-art__eyebrow">{{ __('storefront.common.site_name') }}</p>
                    <h2>{{ __('storefront.home.hero.eyebrow') }}</h2>
                    <p>{{ __('storefront.home.hero.trust_local') }}</p>
                </div>
                <img src="{{ asset('images/homepage-scenes/hero.png') }}" alt="" class="sf-auth-art__image">
                <div class="sf-auth-art__trust">
                    <span><b>✦</b>{{ __('storefront.home.hero.trust_verified') }}</span>
                    <span><b>◈</b>{{ __('storefront.home.hero.trust_secure') }}</span>
                </div>
            </aside>

            <section class="sf-auth-form" aria-labelledby="auth-heading">
                <header class="sf-auth-form__header">
                    <p class="sf-auth-form__kicker">{{ __('storefront.common.site_name') }}</p>
                    <h1 id="auth-heading">@yield('heading')</h1>
                    @hasSection('subheading')
                        <p>@yield('subheading')</p>
                    @endif
                </header>

                <div class="sf-auth-form__alerts">
                    @if (session('status'))
                        <x-storefront.alert type="success">{{ session('status') }}</x-storefront.alert>
                    @endif
                    @if ($errors->has('login'))
                        <x-storefront.alert type="error">{{ $errors->first('login') }}</x-storefront.alert>
                    @endif
                </div>
                <div class="sf-auth-form__fields">@yield('form')</div>
            </section>
        </div>
    </main>
    <footer class="sf-auth-footer">&copy; {{ now()->year }} {{ __('storefront.common.site_name') }}</footer>
</body>
</html>

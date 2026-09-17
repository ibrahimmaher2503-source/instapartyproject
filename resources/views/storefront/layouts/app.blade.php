{{--
    Storefront base layout.

    Direction and language derive from the resolved locale, so the layout stands on
    its own without shared view state.

    RTL is handled entirely by CSS logical properties (ms-/me-/ps-/pe-/start-/end-)
    plus the --font-sans swap in storefront.css. Never use left/right utilities in a
    storefront view: Arabic will mirror wrong and nothing will fail loudly.
--}}
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
    @hasSection('meta_description')
        <meta name="description" content="@yield('meta_description')">
    @endif

    {{-- Each locale is its own indexable URL; tell crawlers they are alternates. --}}
    @foreach ($storefrontLocaleUrls ?? [] as $alternate => $alternateUrl)
        <link rel="alternate" hreflang="{{ $alternate }}" href="{{ $alternateUrl }}">
    @endforeach

    @vite(['resources/css/storefront.css', 'resources/css/homepage.css', 'resources/js/storefront.js'])

    {{-- The approved storefront fonts are bundled in storefront.css so both locales render reliably. --}}

    {{-- Must follow @vite: re-declares the theme custom properties from the active
         admin-editable token row, overriding the compiled defaults. --}}
    @include('storefront.partials.theme')
</head>
<body class="sf-storefront min-h-screen">
    {{-- First tab stop. Keyboard and screen-reader users skip the nav entirely. --}}
    <a
        href="#main"
        class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded-md focus:bg-primary-500 focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white"
    >
        {{ __('storefront.common.skip_to_content') }}
    </a>

    @include('storefront.partials.header')

    <main id="main">
        @yield('content')
    </main>

    @include('storefront.partials.footer')
</body>
</html>

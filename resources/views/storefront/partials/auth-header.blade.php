@php
    $current = app()->getLocale();
    $branding = app(App\Modules\Shared\Application\Actions\GetBrandingAction::class)->execute($current);
    $localeNames = ['en' => 'English', 'ar' => 'العربية'];
@endphp

<header class="sf-auth-header">
    <div class="sf-auth-header__inner">
        <a href="{{ route('storefront.home') }}" class="sf-auth-header__brand">
            @if ($branding['assets']['logo_light'])
                <img src="{{ $branding['assets']['logo_light'] }}" alt="{{ $branding['site_name'] }}" width="160" height="36">
            @else
                {{ $branding['site_name'] }}
            @endif
        </a>

        <div class="sf-auth-header__actions">
            <a href="{{ route('storefront.home') }}" class="sf-auth-header__back">{{ __('storefront.auth.common.back_home') }}</a>
            <nav class="sf-auth-header__locales" aria-label="{{ __('storefront.nav.language') }}">
                @foreach ($storefrontLocaleUrls ?? [] as $locale => $localeUrl)
                    <a href="{{ $localeUrl }}" hreflang="{{ $locale }}" lang="{{ $locale }}" @if ($locale === $current) aria-current="true" @endif>
                        {{ $localeNames[$locale] ?? $locale }}
                    </a>
                @endforeach
            </nav>
        </div>
    </div>
</header>

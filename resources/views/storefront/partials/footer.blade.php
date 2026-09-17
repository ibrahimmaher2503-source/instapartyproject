@php
    $current = app()->getLocale();
    $branding = app(App\Modules\Shared\Application\Actions\GetBrandingAction::class)->execute($current);
    $footerMenus = collect([
        app(App\Modules\Shared\Application\Actions\GetNavigationMenuAction::class)
            ->execute(App\Modules\Shared\Domain\Enums\NavigationSlot::FooterPrimary, $current),
        app(App\Modules\Shared\Application\Actions\GetNavigationMenuAction::class)
            ->execute(App\Modules\Shared\Domain\Enums\NavigationSlot::FooterSecondary, $current),
    ])->filter(static fn (array $menu): bool => filled($menu['items'] ?? []))->values();

    $navigationHref = static function (array $item) use ($current): string {
        $target = ltrim((string) $item['target_value'], '/');

        return match ($item['target_type']) {
            'internal_path' => url('/'.$current.($target === '' ? '' : '/'.$target)),
            'cms_page' => url('/'.$current.'/p/'.$target),
            'category' => url('/'.$current.'/c/'.$target),
            'occasion' => url('/'.$current.'/o/'.$target),
            'external_url' => filter_var($item['target_value'], FILTER_VALIDATE_URL)
                && in_array(parse_url($item['target_value'], PHP_URL_SCHEME), ['http', 'https'], true)
                ? $item['target_value']
                : '#',
            default => '#',
        };
    };

    $localeNames = ['en' => 'English', 'ar' => 'العربية'];
@endphp

<footer class="sf-footer">
    <div class="sf-footer__main sf-home-container">
        <div class="sf-footer__brand">
            <a href="{{ route('storefront.home') }}" class="sf-footer__logo">
                @if ($branding['assets']['logo_light'])
                    <img src="{{ $branding['assets']['logo_light'] }}" alt="{{ $branding['site_name'] }}" width="160" height="36" loading="lazy">
                @else
                    {{ $branding['site_name'] }}
                @endif
            </a>
            @if (filled($branding['tagline']))
                <p>{{ $branding['tagline'] }}</p>
            @endif
            <a class="sf-footer__quiet-link" href="{{ url('/'.$current.'/search') }}">
                {{ __('storefront.nav.search') }}
                <span aria-hidden="true">→</span>
            </a>
        </div>

        @forelse ($footerMenus as $menu)
            <nav class="sf-footer__column" aria-label="{{ $menu['name'] }}">
                <h2>{{ $menu['name'] }}</h2>
                <ul>
                    @foreach ($menu['items'] as $item)
                        <li>
                            <a href="{{ $navigationHref($item) }}" @if ($item['opens_in_new_tab']) target="_blank" rel="noreferrer" @endif>
                                {{ $item['label'] }}
                            </a>
                            @foreach ($item['children'] as $child)
                                <a class="ms-3" href="{{ $navigationHref($child) }}" @if ($child['opens_in_new_tab']) target="_blank" rel="noreferrer" @endif>
                                    {{ $child['label'] }}
                                </a>
                            @endforeach
                        </li>
                    @endforeach
                </ul>
            </nav>
        @empty
            <nav class="sf-footer__column" aria-label="{{ __('storefront.footer.explore') }}">
                <h2>{{ __('storefront.footer.explore') }}</h2>
                <ul>
                    <li><a href="{{ url('/'.$current.'/search') }}">{{ __('storefront.nav.search') }}</a></li>
                    <li><a href="{{ url('/'.$current.'/wizard') }}">{{ __('storefront.nav.wizard') }}</a></li>
                    <li><a href="{{ route('storefront.auth.register') }}">{{ __('storefront.nav.register') }}</a></li>
                </ul>
            </nav>
            <nav class="sf-footer__column" aria-label="{{ __('storefront.footer.account') }}">
                <h2>{{ __('storefront.footer.account') }}</h2>
                <ul>
                    <li><a href="{{ route('storefront.auth.login') }}">{{ __('storefront.nav.login') }}</a></li>
                    <li><a href="{{ url('/'.$current.'/p/faq') }}">{{ __('storefront.footer.faq') }}</a></li>
                </ul>
            </nav>
        @endforelse

        <div class="sf-footer__column sf-footer__contact">
            <h2>{{ __('storefront.footer.contact') }}</h2>
            @if (filled($branding['support_email']))
                <a href="mailto:{{ $branding['support_email'] }}"><bdi dir="ltr">{{ $branding['support_email'] }}</bdi></a>
            @endif
            @if (filled($branding['support_phone']))
                <a href="tel:{{ $branding['support_phone'] }}"><bdi dir="ltr">{{ $branding['support_phone'] }}</bdi></a>
            @endif
            @if (filled($branding['address_line']))
                <p>{{ $branding['address_line'] }}</p>
            @endif
        </div>
    </div>

    <div class="sf-footer__meta sf-home-container">
        <p>&copy; {{ now()->year }} {{ $branding['site_name'] }}</p>
        <nav class="sf-footer__locales" aria-label="{{ __('storefront.nav.language') }}">
            @foreach ($storefrontLocaleUrls ?? [] as $locale => $localeUrl)
                <a href="{{ $localeUrl }}" hreflang="{{ $locale }}" lang="{{ $locale }}" @if ($locale === $current) aria-current="true" @endif>
                    {{ $localeNames[$locale] ?? $locale }}
                </a>
            @endforeach
        </nav>
    </div>
</footer>

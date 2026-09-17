@php
    $current = app()->getLocale();
    $path = '/'.ltrim(request()->path(), '/');

    $headerMenu = app(App\Modules\Shared\Application\Actions\GetNavigationMenuAction::class)
        ->execute(App\Modules\Shared\Domain\Enums\NavigationSlot::Header, $current);
    $mobileMenu = app(App\Modules\Shared\Application\Actions\GetNavigationMenuAction::class)
        ->execute(App\Modules\Shared\Domain\Enums\NavigationSlot::MobileDrawer, $current);
    $branding = app(App\Modules\Shared\Application\Actions\GetBrandingAction::class)->execute($current);

    $navigationItems = $headerMenu['items'];
    $mobileNavigationItems = $mobileMenu['items'] ?: $navigationItems;

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

    $isActive = static function (array $item) use ($current, $path): bool {
        if ($item['target_type'] !== 'internal_path') {
            return false;
        }

        $target = trim((string) $item['target_value'], '/');

        return $path === '/'.$current.($target === '' ? '' : '/'.$target);
    };

    // $storefrontLocaleUrls comes from SetStorefrontLocaleMiddleware and always
    // points to this same page in each supported storefront locale.
    $localeNames = ['en' => 'English', 'ar' => 'العربية'];
@endphp

<header class="sf-header sticky top-0 z-40 border-b border-ink-200/75" data-storefront-header>
    <div class="mx-auto flex h-[4.75rem] max-w-7xl items-center gap-2 px-4 sm:px-6 lg:h-[5.25rem] lg:px-8">
        <button
            type="button"
            class="-ms-2 inline-flex size-11 items-center justify-center rounded-full text-ink-700 transition-colors hover:bg-primary-50 hover:text-primary-600 md:hidden"
            aria-label="{{ __('storefront.common.menu') }}"
            aria-controls="storefront-navigation-drawer"
            data-storefront-nav-open
        >
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
            </svg>
        </button>

        <a
            href="{{ route('storefront.home') }}"
            class="flex min-w-0 items-center text-lg font-bold tracking-[-0.025em] text-primary-600 transition-opacity hover:opacity-80 sm:text-xl"
        >
            @if ($branding['assets']['logo_light'])
                <img
                    src="{{ $branding['assets']['logo_light'] }}"
                    alt="{{ $branding['site_name'] }}"
                    class="h-8 w-auto max-w-40 object-contain sm:h-9"
                    width="160"
                    height="36"
                    fetchpriority="high"
                >
            @else
                {{ $branding['site_name'] }}
            @endif
        </a>

        <nav class="hidden flex-1 items-center justify-center gap-7 px-8 md:flex lg:gap-9" aria-label="{{ __('storefront.common.menu') }}">
            @foreach ($navigationItems as $item)
                @if ($item['children'])
                    <details class="group relative">
                        <summary class="sf-nav-link flex cursor-pointer list-none items-center gap-1.5 py-2 text-sm font-medium text-ink-600 transition-colors hover:text-primary-600">
                            <span>{{ $item['label'] }}</span>
                            <svg class="size-3.5 transition-transform group-open:rotate-180" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m3 6 5 5 5-5" />
                            </svg>
                        </summary>
                        <div class="absolute top-full z-50 mt-3 min-w-52 rounded-xl border border-ink-200 bg-white p-2 shadow-deep">
                            @foreach ($item['children'] as $child)
                                <a
                                    href="{{ $navigationHref($child) }}"
                                    @if ($child['opens_in_new_tab']) target="_blank" rel="noreferrer" @endif
                                    class="block rounded-lg px-3 py-2.5 text-sm font-medium text-ink-600 transition-colors hover:bg-primary-50 hover:text-primary-600"
                                >
                                    {{ $child['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </details>
                @else
                    <a
                        href="{{ $navigationHref($item) }}"
                        @if ($item['opens_in_new_tab']) target="_blank" rel="noreferrer" @endif
                        @if ($isActive($item)) aria-current="page" @endif
                        class="sf-nav-link py-2 text-sm font-medium text-ink-600 transition-colors hover:text-primary-600 aria-[current=page]:text-primary-600"
                    >
                        {{ $item['label'] }}
                    </a>
                @endif
            @endforeach
        </nav>

        <div class="ms-auto flex items-center gap-0.5 sm:gap-1">
            <nav class="hidden items-center rounded-full border border-ink-200 bg-white/70 p-0.5 sm:flex" aria-label="{{ __('storefront.nav.language') }}">
                @foreach ($storefrontLocaleUrls ?? [] as $locale => $localeUrl)
                    <a
                        href="{{ $localeUrl }}"
                        hreflang="{{ $locale }}"
                        lang="{{ $locale }}"
                        @if ($locale === $current) aria-current="true" @endif
                        @class([
                            'rounded-full px-3 py-1.5 text-sm font-medium transition-colors duration-150',
                            'bg-primary-50 text-primary-600' => $locale === $current,
                            'text-ink-500 hover:bg-ink-100 hover:text-ink-900' => $locale !== $current,
                        ])
                    >
                        {{ $localeNames[$locale] ?? $locale }}
                    </a>
                @endforeach
            </nav>

            <a
                href="{{ url('/'.$current.'/search') }}"
                class="inline-flex size-11 items-center justify-center rounded-full text-ink-600 transition-colors hover:bg-primary-50 hover:text-primary-600"
                aria-label="{{ __('storefront.common.search') }}"
            >
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="10.75" cy="10.75" r="5.75" />
                    <path stroke-linecap="round" d="m15.25 15.25 4.25 4.25" />
                </svg>
            </a>

            <a
                href="{{ route('storefront.cart') }}"
                class="inline-flex size-11 items-center justify-center rounded-full text-ink-600 transition-colors hover:bg-primary-50 hover:text-primary-600"
                aria-label="{{ __('storefront.nav.cart') }}"
            >
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h2l1.4 9.2a2 2 0 0 0 2 1.8h6.9a2 2 0 0 0 1.9-1.4L20 8H7" />
                    <circle cx="10" cy="19" r="1" /><circle cx="17" cy="19" r="1" />
                </svg>
            </a>

            @auth
                @if(auth()->user()->hasRole('customer'))
                    <a href="{{ route('storefront.account.dashboard') }}" class="ms-1 inline-flex min-h-11 items-center rounded-full bg-primary-50 px-4 text-sm font-bold text-primary-700">{{ __('account.title') }}</a>
                @endif
                <form method="POST" action="{{ route('storefront.auth.logout') }}" class="ms-1">
                    @csrf
                    <button
                        type="submit"
                        class="hidden rounded-full px-4 py-2 text-sm font-medium text-ink-600 transition-colors duration-150 hover:bg-ink-100 hover:text-ink-900 sm:inline-flex"
                    >
                        {{ __('storefront.nav.logout') }}
                    </button>
                </form>
            @else
                <a
                    href="{{ route('storefront.auth.login') }}"
                    class="ms-1 inline-flex rounded-full border border-ink-300 bg-white/80 px-3.5 py-2 text-sm font-medium text-ink-700 transition-colors duration-150 hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600 sm:px-4"
                >
                    {{ __('storefront.nav.login') }}
                </a>
            @endauth
        </div>
    </div>
</header>

<dialog id="storefront-navigation-drawer" class="sf-nav-drawer" aria-label="{{ __('storefront.common.menu') }}" data-storefront-nav-dialog>
    <div class="flex min-h-full flex-col p-5">
        <div class="flex items-center justify-between gap-4 border-b border-ink-200 pb-5">
            <a href="{{ route('storefront.home') }}" class="min-w-0 text-lg font-bold tracking-[-0.025em] text-primary-600">
                {{ $branding['site_name'] }}
            </a>
            <button
                type="button"
                class="inline-flex size-11 shrink-0 items-center justify-center rounded-full text-ink-700 transition-colors hover:bg-primary-50 hover:text-primary-600"
                aria-label="{{ __('storefront.common.close') }}"
                data-storefront-nav-close
            >
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" />
                </svg>
            </button>
        </div>

        <nav class="flex flex-1 flex-col gap-1 py-6" aria-label="{{ __('storefront.common.menu') }}">
            @foreach ($mobileNavigationItems as $item)
                <a
                    href="{{ $navigationHref($item) }}"
                    @if ($item['opens_in_new_tab']) target="_blank" rel="noreferrer" @endif
                    @if ($isActive($item)) aria-current="page" @endif
                    class="rounded-xl px-4 py-3 text-base font-medium text-ink-700 transition-colors hover:bg-primary-50 hover:text-primary-600 aria-[current=page]:bg-primary-50 aria-[current=page]:text-primary-600"
                >
                    {{ $item['label'] }}
                </a>
                @foreach ($item['children'] as $child)
                    <a
                        href="{{ $navigationHref($child) }}"
                        @if ($child['opens_in_new_tab']) target="_blank" rel="noreferrer" @endif
                        class="ms-4 rounded-xl px-4 py-2.5 text-sm font-medium text-ink-500 transition-colors hover:bg-primary-50 hover:text-primary-600"
                    >
                        {{ $child['label'] }}
                    </a>
                @endforeach
            @endforeach
        </nav>

        <div class="border-t border-ink-200 pt-5">
            <div class="flex items-center gap-2">
                @foreach ($storefrontLocaleUrls ?? [] as $locale => $localeUrl)
                    <a
                        href="{{ $localeUrl }}"
                        hreflang="{{ $locale }}"
                        lang="{{ $locale }}"
                        @if ($locale === $current) aria-current="true" @endif
                        @class([
                            'flex-1 rounded-full px-3 py-2.5 text-center text-sm font-medium transition-colors',
                            'bg-primary-50 text-primary-600' => $locale === $current,
                            'bg-ink-100 text-ink-600 hover:bg-ink-200' => $locale !== $current,
                        ])
                    >
                        {{ $localeNames[$locale] ?? $locale }}
                    </a>
                @endforeach
            </div>

            <a
                href="{{ route('storefront.auth.login') }}"
                class="mt-3 flex w-full items-center justify-center rounded-full border border-ink-300 bg-white px-4 py-3 text-sm font-medium text-ink-700 transition-colors hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600"
            >
                {{ __('storefront.nav.login') }}
            </a>
        </div>
    </div>
</dialog>

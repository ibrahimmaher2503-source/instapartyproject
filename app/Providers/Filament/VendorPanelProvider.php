<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Modules\Identity\Filament\Auth\IsolatedPanelLogin;
use App\Modules\Identity\Filament\Vendor\Pages\RegisterVendorPage;
use App\Modules\Identity\Http\Middleware\CheckVendorSuspension;
use App\Modules\Identity\Http\Middleware\EnforcePanelIntendedUrl;
use App\Modules\Identity\Http\Middleware\SetLocaleMiddleware;
use App\Modules\Shared\Filament\RenderHooks\HtmlDirectionRenderHook;
use App\Modules\Shared\Filament\RenderHooks\NotificationHeadingA11yRenderHook;
use App\Modules\Shared\Filament\Vendor\Pages\VendorDashboardPage;
use App\Modules\Shared\Http\Middleware\SetHtmlDirectionMiddleware;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\SpatieLaravelTranslatablePlugin;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class VendorPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch): void {
            $switch
                ->locales(['en', 'ar'])
                ->labels([
                    'en' => 'English',
                    'ar' => 'العربية',
                ])
                ->visible(insidePanels: true);
        });

        HtmlDirectionRenderHook::register();
        NotificationHeadingA11yRenderHook::register();
    }

    public function panel(Panel $panel): Panel
    {
        if (config('app.minimal_filament_panels', false)) {
            return $panel
                ->id('vendor')
                ->path('vendor-portal')
                ->login(IsolatedPanelLogin::class)
                ->homeUrl('/vendor-portal')
                ->brandName('InstaParty Vendor')
                ->pages([
                    VendorDashboardPage::class,
                ])
                ->widgets([
                    AccountWidget::class,
                    'App\\Modules\\Identity\Filament\Vendor\\Widgets\\VendorOnboardingChecklistWidget',
                    'App\\Modules\\Shared\Filament\Vendor\\Widgets\\VendorStatsOverviewWidget',
                    'App\\Modules\\Shared\Filament\Vendor\\Widgets\\VendorRecentBookingsWidget',
                ])
                ->authGuard('vendor')
                ->middleware([
                    EncryptCookies::class,
                    AddQueuedCookiesToResponse::class,
                    StartSession::class,
                    EnforcePanelIntendedUrl::class,
                    AuthenticateSession::class,
                    ShareErrorsFromSession::class,
                    VerifyCsrfToken::class,
                    SubstituteBindings::class,
                    SetLocaleMiddleware::class,
                    SetHtmlDirectionMiddleware::class,
                    DisableBladeIconComponents::class,
                    DispatchServingFilamentEvent::class,
                ])
                ->authMiddleware([
                    Authenticate::class,
                    CheckVendorSuspension::class,
                ]);
        }

        return $panel
            ->id('vendor')
            ->path('vendor-portal')
            ->login(IsolatedPanelLogin::class)
            ->registration(RegisterVendorPage::class)
            ->emailVerification()
            ->homeUrl('/vendor-portal')
            ->passwordReset()
            ->brandName('InstaParty Vendor')
            ->colors([
                // Jewel-grade amber-orange — richer saturation at 400/500/600 than
                // the generic Tailwind Orange preset; glows cleanly in dark mode too.
                'primary' => [
                    50 => '#fff8ed',
                    100 => '#ffefd5',
                    200 => '#fed9a1',
                    300 => '#fdb96e',
                    400 => '#fb8f35',
                    500 => '#f96b0e',
                    600 => '#e4510b',
                    700 => '#bd3c0c',
                    800 => '#963110',
                    900 => '#792a11',
                    950 => '#421206',
                ],
                // Warm stone gray — complements the amber hue without the cold
                // blue cast of Zinc/Slate that clashes with an orange primary.
                'gray' => [
                    50 => '#fafaf8',
                    100 => '#f4f3ef',
                    200 => '#e8e6e0',
                    300 => '#d4d0c8',
                    400 => '#aaa49a',
                    500 => '#7d786f',
                    600 => '#5e5a53',
                    700 => '#46433d',
                    800 => '#2e2c28',
                    900 => '#1c1b18',
                    950 => '#100f0d',
                ],
                'success' => [
                    50 => '#ecfdf5',
                    100 => '#d1fae5',
                    200 => '#a7f3d0',
                    300 => '#6ee7b7',
                    400 => '#34d399',
                    500 => '#10b981',
                    600 => '#059669',
                    700 => '#047857',
                    800 => '#065f46',
                    900 => '#064e3b',
                    950 => '#022c22',
                ],
                'danger' => [
                    50 => '#fff1f2',
                    100 => '#ffe4e6',
                    200 => '#fecdd3',
                    300 => '#fda4af',
                    400 => '#fb7185',
                    500 => '#f43f5e',
                    600 => '#e11d48',
                    700 => '#be123c',
                    800 => '#9f1239',
                    900 => '#881337',
                    950 => '#4c0519',
                ],
                // Amber warning — shifted yellower than the orange primary so
                // the two are clearly distinct in badges and alerts.
                'warning' => [
                    50 => '#fffbeb',
                    100 => '#fef3c7',
                    200 => '#fde68a',
                    300 => '#fcd34d',
                    400 => '#fbbf24',
                    500 => '#f59e0b',
                    600 => '#d97706',
                    700 => '#b45309',
                    800 => '#92400e',
                    900 => '#78350f',
                    950 => '#451a03',
                ],
                'info' => [
                    50 => '#f0f9ff',
                    100 => '#e0f2fe',
                    200 => '#bae6fd',
                    300 => '#7dd3fc',
                    400 => '#38bdf8',
                    500 => '#0ea5e9',
                    600 => '#0284c7',
                    700 => '#0369a1',
                    800 => '#075985',
                    900 => '#0c4a6e',
                    950 => '#082f49',
                ],
            ])
            ->colors([
                'primary' => Color::Teal,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Blue,
                'gray' => Color::Slate,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->font('Cairo')
            ->viteTheme('resources/css/app.css')
            // Resources
            ->discoverResources(
                in: app_path('Modules/Identity/Filament/Vendor/Resources'),
                for: 'App\\Modules\\Identity\Filament\Vendor\\Resources'
            )
            ->discoverResources(
                in: app_path('Modules/Catalog/Filament/Vendor/Resources'),
                for: 'App\\Modules\\Catalog\Filament\Vendor\\Resources'
            )
            ->discoverResources(
                in: app_path('Modules/Booking/Filament/Vendor/Resources'),
                for: 'App\\Modules\\Booking\Filament\Vendor\\Resources'
            )
            ->discoverResources(
                in: app_path('Modules/Settlement/Filament/Vendor/Resources'),
                for: 'App\\Modules\\Settlement\Filament\Vendor\\Resources'
            )
            ->discoverResources(
                in: app_path('Modules/Communication/Filament/Vendor/Resources'),
                for: 'App\\Modules\\Communication\Filament\Vendor\\Resources'
            )
            ->discoverResources(
                in: app_path('Modules/Reviews/Filament/Vendor/Resources'),
                for: 'App\\Modules\\Reviews\Filament\Vendor\\Resources'
            )
            ->discoverResources(
                in: app_path('Modules/Loyalty/Filament/Vendor/Resources'),
                for: 'App\\Modules\\Loyalty\Filament\Vendor\\Resources'
            )
            // Pages
            ->discoverPages(
                in: app_path('Modules/Identity/Filament/Vendor/Pages'),
                for: 'App\\Modules\\Identity\Filament\Vendor\\Pages'
            )
            ->discoverPages(
                in: app_path('Modules/Catalog/Filament/Vendor/Pages'),
                for: 'App\\Modules\\Catalog\Filament\Vendor\\Pages'
            )
            ->discoverPages(
                in: app_path('Modules/Booking/Filament/Vendor/Pages'),
                for: 'App\\Modules\\Booking\Filament\Vendor\\Pages'
            )
            ->discoverPages(
                in: app_path('Modules/Settlement/Filament/Vendor/Pages'),
                for: 'App\\Modules\\Settlement\Filament\Vendor\\Pages'
            )
            ->discoverPages(
                in: app_path('Modules/Communication/Filament/Vendor/Pages'),
                for: 'App\\Modules\\Communication\Filament\Vendor\\Pages'
            )
            ->discoverPages(
                in: app_path('Modules/Reviews/Filament/Vendor/Pages'),
                for: 'App\\Modules\\Reviews\Filament\Vendor\\Pages'
            )
            ->discoverPages(
                in: app_path('Modules/Loyalty/Filament/Vendor/Pages'),
                for: 'App\\Modules\\Loyalty\Filament\Vendor\\Pages'
            )
            ->discoverPages(
                in: app_path('Modules/Shared/Filament/Vendor/Pages'),
                for: 'App\\Modules\\Shared\Filament\Vendor\\Pages'
            )
            // Widgets
            ->discoverWidgets(
                in: app_path('Modules/Booking/Filament/Vendor/Widgets'),
                for: 'App\\Modules\\Booking\Filament\Vendor\\Widgets'
            )
            ->discoverWidgets(
                in: app_path('Modules/Identity/Filament/Vendor/Widgets'),
                for: 'App\\Modules\\Identity\Filament\Vendor\\Widgets'
            )
            ->discoverWidgets(
                in: app_path('Modules/Shared/Filament/Vendor/Widgets'),
                for: 'App\\Modules\\Shared\Filament\Vendor\\Widgets'
            )
            ->pages([
                VendorDashboardPage::class,
            ])
            ->widgets([
                AccountWidget::class,
            ])
            ->navigationGroups([
                'profile' => NavigationGroup::make()
                    ->label(fn (): string => __('vendor-portal.nav.groups.profile')),
                'services' => NavigationGroup::make()
                    ->label(fn (): string => __('vendor-portal.nav.groups.services'))->collapsed(),
                'bookings' => NavigationGroup::make()
                    ->label(fn (): string => __('vendor-portal.nav.groups.bookings'))->collapsed(),
                'finance' => NavigationGroup::make()
                    ->label(fn (): string => __('vendor-portal.nav.groups.finance'))->collapsed(),
                'engagement' => NavigationGroup::make()
                    ->label(fn (): string => __('vendor-portal.nav.groups.engagement'))->collapsed(),
                'settings' => NavigationGroup::make()
                    ->label(fn (): string => __('vendor-portal.nav.groups.settings'))->collapsed(),
            ])
            ->plugins([
                SpatieLaravelTranslatablePlugin::make()
                    ->defaultLocales(['en', 'ar']),
            ])
            ->authGuard('vendor')
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->unsavedChangesAlerts(false)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                EnforcePanelIntendedUrl::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                SetLocaleMiddleware::class,
                SetHtmlDirectionMiddleware::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                CheckVendorSuspension::class,
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Modules\Identity\Filament\Auth\IsolatedPanelLogin;
use App\Modules\Identity\Http\Middleware\EnforcePanelIntendedUrl;
use App\Modules\Identity\Http\Middleware\SetLocaleMiddleware;
use App\Modules\Shared\Filament\RenderHooks\HtmlDirectionRenderHook;
use App\Modules\Shared\Filament\RenderHooks\TutorialLauncherRenderHook;
use App\Modules\Shared\Http\Middleware\SetHtmlDirectionMiddleware;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Infolists\Infolist;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\SpatieLaravelTranslatablePlugin;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Table;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Rmsramos\Activitylog\ActivitylogPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        Table::$defaultDateTimeDisplayFormat = 'j M Y, g:i A';
        Infolist::$defaultDateTimeDisplayFormat = 'j M Y, g:i A';

        Table::configureUsing(fn (Table $table): Table => $table
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->searchDebounce('400ms')
            ->emptyStateIcon('heroicon-o-inbox')
            ->emptyStateHeading(__('admin.empty_states.no_records'))
            ->emptyStateDescription(__('admin.empty_states.no_records_description')));

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch): void {
            $switch
                ->locales(['en', 'ar'])
                ->visible(insidePanels: true);
        });

        HtmlDirectionRenderHook::register();
        TutorialLauncherRenderHook::register();
    }

    public function panel(Panel $panel): Panel
    {
        if (config('app.minimal_filament_panels', false)) {
            return $panel
                ->default()
                ->id('admin')
                ->path('admin')
                ->brandName('InstaParty')
                ->login(IsolatedPanelLogin::class)
                ->authGuard('web')
                ->pages([
                    Dashboard::class,
                ])
                ->widgets([
                    'App\\Modules\\Shared\\Filament\\Widgets\\AdminDashboardSectionsWidget',
                    'App\\Modules\\Shared\Filament\Widgets\\AdminDashboardHeaderWidget',
                    'App\\Modules\\Identity\Filament\Widgets\\PendingVendorApprovalsWidget',
                    'App\\Modules\\Booking\Filament\Widgets\\BookingsWaitingCustomerApprovalWidget',
                    'App\\Modules\\Booking\Filament\Widgets\\LateVendorResponsesWidget',
                    'App\\Modules\\Catalog\Filament\Widgets\\PendingServiceModerationWidget',
                    'App\\Modules\\Catalog\Filament\Widgets\\PendingServiceEditsBadgeWidget',
                    'App\\Modules\\Catalog\Filament\Widgets\\ExcelImportsWithErrorsWidget',
                    'App\\Modules\\Payments\Filament\Widgets\\FailedPaymentsWidget',
                    'App\\Modules\\Settlement\Filament\Widgets\\PendingWithdrawalsWidget',
                    'App\\Modules\\Communication\Filament\Widgets\\CriticalAdminInboxWidget',
                    'App\\Modules\\Communication\Filament\Widgets\\FailedNotificationDispatchesWidget',
                    'App\\Modules\\Advertising\Filament\Widgets\\AdvertisingStatsWidget',
                    'App\\Modules\\Subscriptions\Filament\Widgets\\SubscriptionStatsWidget',
                    'App\\Modules\\Subscriptions\Filament\Widgets\\PastDueSubscriptionsStatWidget',
                ])
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
                ]);
        }

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('InstaParty')
            ->login(IsolatedPanelLogin::class)
            ->authGuard('web')
            ->colors([
                'primary' => Color::hex('#1C2B54'),
                'success' => Color::hex('#087F5B'),
                'warning' => Color::hex('#A56A10'),
                'danger' => Color::hex('#B73A4B'),
                'info' => Color::hex('#3F67A6'),
                'gray' => Color::Slate,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('264px')
            ->collapsedSidebarWidth('72px')
            ->maxContentWidth(MaxWidth::Full)
            ->globalSearchDebounce('400ms')
            ->globalSearchKeyBindings(['ctrl+k', 'command+k'])
            ->font('Cairo')
            // Flat Filament/Resources/ paths (existing resources — do not move)
            ->discoverResources(in: app_path('Modules/Booking/Filament/Resources'), for: 'App\\Modules\\Booking\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Catalog/Filament/Resources'), for: 'App\\Modules\\Catalog\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Communication/Filament/Resources'), for: 'App\\Modules\\Communication\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Discovery/Filament/Resources'), for: 'App\\Modules\\Discovery\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Geography/Filament/Resources'), for: 'App\\Modules\\Geography\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Identity/Filament/Resources'), for: 'App\\Modules\\Identity\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Loyalty/Filament/Resources'), for: 'App\\Modules\\Loyalty\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Payments/Filament/Resources'), for: 'App\\Modules\\Payments\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Reviews/Filament/Resources'), for: 'App\\Modules\\Reviews\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Settlement/Filament/Resources'), for: 'App\\Modules\\Settlement\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Subscriptions/Filament/Resources'), for: 'App\\Modules\\Subscriptions\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Shared/Filament/Resources'), for: 'App\\Modules\\Shared\Filament\Resources')
            // Filament/Admin/Resources/ paths (forward-compatible; new admin resources go here)
            ->discoverResources(in: app_path('Modules/Booking/Filament/Admin/Resources'), for: 'App\\Modules\\Booking\Filament\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Catalog/Filament/Admin/Resources'), for: 'App\\Modules\\Catalog\Filament\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Communication/Filament/Admin/Resources'), for: 'App\\Modules\\Communication\Filament\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Discovery/Filament/Admin/Resources'), for: 'App\\Modules\\Discovery\Filament\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Geography/Filament/Admin/Resources'), for: 'App\\Modules\\Geography\Filament\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Identity/Filament/Admin/Resources'), for: 'App\\Modules\\Identity\Filament\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Loyalty/Filament/Admin/Resources'), for: 'App\\Modules\\Loyalty\Filament\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Payments/Filament/Admin/Resources'), for: 'App\\Modules\\Payments\Filament\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Reviews/Filament/Admin/Resources'), for: 'App\\Modules\\Reviews\Filament\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Settlement/Filament/Admin/Resources'), for: 'App\\Modules\\Settlement\Filament\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Subscriptions/Filament/Admin/Resources'), for: 'App\\Modules\\Subscriptions\Filament\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Shared/Filament/Admin/Resources'), for: 'App\\Modules\\Shared\Filament\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Tax/Filament/Resources'), for: 'App\\Modules\\Tax\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Advertising/Filament/Resources'), for: 'App\\Modules\\Advertising\Filament\Resources')
            ->discoverResources(in: app_path('Modules/TrustSafety/Filament/Resources'), for: 'App\\Modules\\TrustSafety\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Support/Filament/Resources'), for: 'App\\Modules\\Support\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Promotions/Filament/Resources'), for: 'App\\Modules\\Promotions\Filament\Resources')
            ->discoverPages(in: app_path('Modules/Communication/Filament/Pages'), for: 'App\\Modules\\Communication\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Catalog/Filament/Pages'), for: 'App\\Modules\\Catalog\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Payments/Filament/Pages'), for: 'App\\Modules\\Payments\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Reviews/Filament/Pages'), for: 'App\\Modules\\Reviews\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Shared/Filament/Pages'), for: 'App\\Modules\\Shared\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Reporting/Filament/Pages'), for: 'App\\Modules\\Reporting\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Tax/Filament/Pages'), for: 'App\\Modules\\Tax\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Advertising/Filament/Pages'), for: 'App\\Modules\\Advertising\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Settlement/Filament/Pages'), for: 'App\\Modules\\Settlement\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Subscriptions/Filament/Pages'), for: 'App\\Modules\\Subscriptions\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->discoverWidgets(in: app_path('Modules/Booking/Filament/Widgets'), for: 'App\\Modules\\Booking\Filament\Widgets')
            ->discoverWidgets(in: app_path('Modules/Subscriptions/Filament/Widgets'), for: 'App\\Modules\\Subscriptions\Filament\Widgets')
            ->discoverWidgets(in: app_path('Modules/Shared/Filament/Widgets'), for: 'App\\Modules\\Shared\Filament\Widgets')
            ->discoverWidgets(in: app_path('Modules/Reporting/Filament/Widgets'), for: 'App\\Modules\\Reporting\Filament\Widgets')
            ->discoverWidgets(in: app_path('Modules/Advertising/Filament/Widgets'), for: 'App\\Modules\\Advertising\Filament\Widgets')
            ->discoverWidgets(in: app_path('Modules/Settlement/Filament/Widgets'), for: 'App\\Modules\\Settlement\Filament\Widgets')
            ->discoverWidgets(in: app_path('Modules/Identity/Filament/Widgets'), for: 'App\\Modules\\Identity\Filament\Widgets')
            ->discoverWidgets(in: app_path('Modules/Catalog/Filament/Widgets'), for: 'App\\Modules\\Catalog\Filament\Widgets')
            ->discoverWidgets(in: app_path('Modules/Payments/Filament/Widgets'), for: 'App\\Modules\\Payments\Filament\Widgets')
            ->discoverWidgets(in: app_path('Modules/Communication/Filament/Widgets'), for: 'App\\Modules\\Communication\Filament\Widgets')
            ->viteTheme('resources/css/app.css')
            ->unsavedChangesAlerts(false)
            ->navigationGroups([
                NavigationGroup::make(__('admin.nav.groups.operations'))
                    ->label(fn (): string => __('admin.nav.groups.operations')),
                NavigationGroup::make(__('admin.nav.groups.vendor_management'))
                    ->label(fn (): string => __('admin.nav.groups.vendor_management'))->collapsed(),
                NavigationGroup::make(__('admin.nav.groups.services'))
                    ->label(fn (): string => __('admin.nav.groups.services'))->collapsed(),
                NavigationGroup::make(__('admin.nav.groups.payments'))
                    ->label(fn (): string => __('admin.nav.groups.payments'))->collapsed(),
                NavigationGroup::make(__('admin.nav.groups.support'))
                    ->label(fn (): string => __('admin.nav.groups.support'))->collapsed(),
                NavigationGroup::make(__('admin.nav.groups.communication'))
                    ->label(fn (): string => __('admin.nav.groups.communication'))->collapsed(),
                NavigationGroup::make(__('admin.nav.groups.content'))
                    ->label(fn (): string => __('admin.nav.groups.content'))->collapsed(),
                NavigationGroup::make(__('admin.nav.groups.security'))
                    ->label(fn (): string => __('admin.nav.groups.security'))->collapsed(),
                NavigationGroup::make(__('admin.nav.groups.settings'))
                    ->label(fn (): string => __('admin.nav.groups.settings'))->collapsed(),
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
                ActivitylogPlugin::make()
                    ->label(fn (): string => __('admin.activity_log.singular'))
                    ->pluralLabel(fn (): string => __('admin.activity_log.plural'))
                    ->navigationGroup(fn (): string => __('admin.nav.groups.operations'))
                    ->navigationSort(80),

                SpatieLaravelTranslatablePlugin::make()
                    ->defaultLocales(['en', 'ar']),
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
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
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Pages;

use App\Modules\Subscriptions\Filament\Widgets\ExpiringSubscriptionsWidget;
use App\Modules\Subscriptions\Filament\Widgets\PastDueSubscriptionsStatWidget;
use App\Modules\Subscriptions\Filament\Widgets\SubscriptionRevenueChartWidget;
use App\Modules\Subscriptions\Filament\Widgets\SubscriptionStatsWidget;
use App\Modules\Subscriptions\Filament\Widgets\VendorsByTierWidget;
use Filament\Pages\Page;

class SubscriptionAnalyticsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string $view = 'filament.pages.subscription-analytics';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.subscriptions');
    }

    public static function getNavigationLabel(): string
    {
        return __('subscriptions::subscription.analytics');
    }

    public function getTitle(): string
    {
        return __('subscriptions::subscription.analytics');
    }

    public function getHeaderWidgets(): array
    {
        return [
            SubscriptionStatsWidget::class,
            PastDueSubscriptionsStatWidget::class,
            SubscriptionRevenueChartWidget::class,
            VendorsByTierWidget::class,
            ExpiringSubscriptionsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }
}

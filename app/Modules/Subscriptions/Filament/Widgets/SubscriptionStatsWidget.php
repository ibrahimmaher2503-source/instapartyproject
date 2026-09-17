<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SubscriptionStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 120;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $active = DB::table('vendor_subscriptions')->where('status', 'active')->count();

        $mrr = DB::table('vendor_subscriptions')
            ->join('subscription_plans', 'subscription_plans.id', '=', 'vendor_subscriptions.subscription_plan_id')
            ->where('vendor_subscriptions.status', 'active')
            ->sum('subscription_plans.monthly_price_minor');

        $expiringCount = DB::table('vendor_subscriptions')
            ->where('status', 'active')
            ->whereBetween('current_period_end', [now(), now()->addDays(30)])
            ->count();

        $overrideCount = DB::table('vendor_subscriptions')
            ->whereNotNull('override_expires_at')
            ->count();

        return [
            Stat::make(__('subscriptions::subscription.active'), $active)
                ->description(__('subscriptions::subscription.status.active'))
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make(__('subscriptions::subscription.mrr'), 'EGP '.number_format($mrr / 100, 0))
                ->description(__('subscriptions::subscription.billing_cycle.monthly'))
                ->icon('heroicon-o-banknotes')
                ->color('primary'),

            Stat::make(__('subscriptions::subscription.expiring_30_days'), $expiringCount)
                ->description(__('subscriptions::subscription.expiring_soon'))
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make(__('subscriptions::subscription.admin_overrides'), $overrideCount)
                ->description(__('subscriptions::subscription.admin_override_only'))
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('info'),
        ];
    }
}

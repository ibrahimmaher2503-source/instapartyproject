<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Widgets;

use App\Modules\Subscriptions\Domain\Enums\BillingCycle;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubscriptionStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 120;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $at = now();
        $activeQuery = VendorSubscription::query()->active()->effectiveAt($at);
        $active = (clone $activeQuery)->count();

        $paidQuery = (clone $activeQuery)
            ->join('subscription_plans', 'subscription_plans.id', '=', 'vendor_subscriptions.subscription_plan_id')
            ->where('is_admin_override', false);

        $monthlyMinor = (clone $paidQuery)
            ->where('billing_cycle', BillingCycle::Monthly->value)
            ->where('subscription_plans.monthly_price_currency', 'EGP')
            ->sum('subscription_plans.monthly_price_minor');
        $yearlyMinor = (clone $paidQuery)
            ->where('billing_cycle', BillingCycle::Yearly->value)
            ->where('subscription_plans.yearly_price_currency', 'EGP')
            ->sum('subscription_plans.yearly_price_minor');
        $mrr = Money::ofMinor($monthlyMinor, 'EGP')->plus(
            Money::ofMinor($yearlyMinor, 'EGP')->dividedBy(12, RoundingMode::HALF_EVEN),
        );

        $expiringCount = (clone $activeQuery)
            ->whereBetween('current_period_end', [$at, $at->copy()->addDays(30)])
            ->count();

        $overrideCount = (clone $activeQuery)
            ->adminOverride()
            ->count();

        return [
            Stat::make(__('subscriptions::subscription.active'), $active)
                ->description(__('subscriptions::subscription.status.active'))
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make(__('subscriptions::subscription.mrr'), $mrr->formatTo(app()->getLocale()))
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

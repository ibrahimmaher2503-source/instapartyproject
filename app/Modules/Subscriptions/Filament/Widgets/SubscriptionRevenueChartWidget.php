<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SubscriptionRevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = null;

    protected static ?int $sort = 150;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 6,
    ];

    protected static ?string $maxHeight = '18rem';

    public function getHeading(): ?string
    {
        return __('subscriptions::subscription.revenue_by_month');
    }

    public function getDescription(): ?string
    {
        return __('subscriptions::subscription.billing_cycle.monthly').' · '.now()->subMonths(6)->format('M Y').' - '.now()->format('M Y');
    }

    protected function getData(): array
    {
        $month = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', paid_at)"
            : "DATE_FORMAT(paid_at, '%Y-%m')";

        $rows = DB::table('subscription_invoices')
            ->selectRaw("{$month} as month, SUM(amount_minor) as revenue")
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subMonths(6))
            ->groupByRaw($month)
            ->orderBy('month')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => __('subscriptions::subscription.no_revenue_data'),
                        'data' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => __('subscriptions::subscription.chart_revenue_egp'),
                    'data' => $rows->pluck('revenue')->map(fn ($v) => round($v / 100, 2))->all(),
                    'backgroundColor' => 'rgba(249,115,22,0.15)',
                    'borderColor' => '#f97316',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.4,
                    'pointBackgroundColor' => '#f97316',
                ],
            ],
            'labels' => $rows->pluck('month')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'top'],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AdRevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = null;

    protected static ?int $sort = 160;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 6,
    ];

    protected static ?string $maxHeight = '18rem';

    public function getHeading(): ?string
    {
        return __('advertising::advertising.revenue_by_month');
    }

    public function getDescription(): ?string
    {
        return now()->subMonths(6)->format('M Y').' - '.now()->format('M Y');
    }

    protected function getData(): array
    {
        $month = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $rows = DB::table('vendor_ad_subscriptions')
            ->selectRaw("{$month} as month, SUM(total_minor) as revenue")
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupByRaw($month)
            ->orderBy('month')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => __('advertising::advertising.no_revenue_data'),
                        'data' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => __('advertising::advertising.chart_ad_revenue_egp'),
                    'data' => $rows->pluck('revenue')->map(fn ($v) => round($v / 100, 2))->all(),
                    'backgroundColor' => 'rgba(245,158,11,0.75)',
                    'borderColor' => '#f59e0b',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
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
                'legend' => ['display' => false],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

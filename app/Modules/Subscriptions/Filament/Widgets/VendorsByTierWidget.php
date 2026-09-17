<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Widgets;

use App\Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Filament\Widgets\ChartWidget;

class VendorsByTierWidget extends ChartWidget
{
    protected static ?int $sort = 140;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 4,
    ];

    protected static ?string $maxHeight = '18rem';

    public function getHeading(): ?string
    {
        return __('subscriptions::subscription.vendors_by_tier');
    }

    protected function getData(): array
    {
        $data = VendorSubscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->selectRaw('subscription_plan_id, COUNT(*) as count')
            ->groupBy('subscription_plan_id')
            ->with('plan:id,plan_code')
            ->get()
            ->map(fn ($row) => [
                'tier' => $row->plan->plan_code,
                'count' => $row->count,
            ])
            ->sortBy('tier')
            ->values();

        if ($data->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => __('subscriptions::subscription.no_tier_data'),
                        'data' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => __('subscriptions::subscription.chart_active_vendors'),
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => ['#6B7280', '#f97316', '#F59E0B', '#10B981'],
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $data->pluck('tier')->toArray(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['stepSize' => 1, 'precision' => 0]],
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

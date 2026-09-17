<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Widgets;

use App\Modules\Subscriptions\Domain\Enums\InvoiceStatus;
use App\Modules\Subscriptions\Domain\Models\SubscriptionInvoice;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PastDueSubscriptionsStatWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 110;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 3,
        'xl' => 3,
    ];

    protected function getStats(): array
    {
        $pastDueCount = SubscriptionInvoice::query()
            ->where('status', InvoiceStatus::PastDue->value)
            ->count();

        return [
            Stat::make(__('subscriptions::subscription.past_due_stat'), $pastDueCount)
                ->description(__('subscriptions::subscription.past_due_invoices_require_attention'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }

    protected function getColumns(): int
    {
        return 1;
    }
}

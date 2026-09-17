<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Widgets;

use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Filament\Resources\WithdrawalsQueueResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingWithdrawalsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 70;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 3,
        'xl' => 2,
    ];

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_withdrawals_queue') ?? false;
    }

    protected function getStats(): array
    {
        $count = Withdrawal::query()
            ->where('status', WithdrawalStatus::Pending->value)
            ->count();

        return [
            Stat::make(
                label: __('settlement::widgets.pending_withdrawals_heading'),
                value: $count,
            )
                ->description(trans_choice('settlement::widgets.pending_withdrawals_description', $count, ['count' => $count]))
                ->color($count > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-banknotes')
                ->url(env('MINIMAL_FILAMENT_PANELS', false) ? '/admin' : WithdrawalsQueueResource::getUrl('index')),
        ];
    }

    protected function getColumns(): int
    {
        return 1;
    }
}

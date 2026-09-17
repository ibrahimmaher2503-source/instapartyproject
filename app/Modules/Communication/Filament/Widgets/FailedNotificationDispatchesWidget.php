<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Widgets;

use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Filament\Resources\NotificationDispatchResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FailedNotificationDispatchesWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 60;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 3,
        'xl' => 2,
    ];

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_notification_dispatch') ?? false;
    }

    protected function getStats(): array
    {
        $count = NotificationDispatch::query()
            ->whereIn('status', [DispatchStatus::Failed->value, DispatchStatus::Bounced->value])
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        return [
            Stat::make(
                label: __('communication::widgets.failed_notification_dispatches_heading'),
                value: $count,
            )
                ->description(trans_choice('communication::widgets.failed_notification_dispatches_description', $count, ['count' => $count]))
                ->color($count > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-bell-slash')
                ->url(env('MINIMAL_FILAMENT_PANELS', false) ? '/admin' : NotificationDispatchResource::getUrl('index').'?tableFilters[status][value]=failed'),
        ];
    }

    protected function getColumns(): int
    {
        return 1;
    }
}

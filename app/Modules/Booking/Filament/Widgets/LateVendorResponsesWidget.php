<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Widgets;

use App\Modules\Booking\Application\Actions\GetNegotiationMonitorQueryAction;
use App\Modules\Booking\Filament\Resources\BookingsMonitorResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LateVendorResponsesWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 30;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 3,
        'xl' => 2,
    ];

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_bookings::monitor') ?? false;
    }

    protected function getStats(): array
    {
        $count = app(GetNegotiationMonitorQueryAction::class)
            ->execute('late')
            ->count();

        return [
            Stat::make(
                label: __('booking::widgets.late_vendor_responses_heading'),
                value: $count,
            )
                ->description(trans_choice('booking::widgets.late_vendor_responses_description', $count, ['count' => $count]))
                ->color($count > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-clock')
                ->url(env('MINIMAL_FILAMENT_PANELS', false)
                    ? '/admin'
                    : BookingsMonitorResource::getUrl('index').'?tableFilters[negotiation_scope][value]=late'),
        ];
    }

    protected function getColumns(): int
    {
        return 1;
    }
}

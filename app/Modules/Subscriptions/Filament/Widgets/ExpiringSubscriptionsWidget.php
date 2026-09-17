<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Widgets;

use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ExpiringSubscriptionsWidget extends TableWidget
{
    protected static ?string $heading = null;

    protected static ?int $sort = 130;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 8,
    ];

    public function getHeading(): ?string
    {
        return __('subscriptions::subscription.expiring_soon');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                VendorSubscription::query()
                    ->with(['vendor', 'plan'])
                    ->where('status', 'active')
                    ->whereBetween('current_period_end', [now(), now()->addDays(30)])
                    ->orderBy('current_period_end')
            )
            ->columns([
                TextColumn::make('vendor.business_name')
                    ->label(__('subscriptions::subscription.vendor'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),
                TextColumn::make('plan.name')
                    ->label(__('subscriptions::subscription.plan_code'))
                    ->badge()
                    ->color('warning'),
                TextColumn::make('current_period_end')
                    ->label(__('subscriptions::subscription.expires_at'))
                    ->date()
                    ->sortable()
                    ->description(fn (VendorSubscription $record): string => $record->current_period_end->diffForHumans()),
            ])
            ->actions([
                Action::make('remind')
                    ->label(__('subscriptions::subscription.send_renewal_reminder'))
                    ->icon('heroicon-o-bell')
                    ->button()
                    ->size('sm')
                    ->action(function (VendorSubscription $record): void {
                        Notification::make()
                            ->title(__('subscriptions::subscription.renewal_reminder_sent'))
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateIcon('heroicon-o-check-badge')
            ->emptyStateHeading(__('subscriptions::subscription.no_expiring_subs'))
            ->emptyStateDescription(__('subscriptions::subscription.no_expiring_subs_desc'))
            ->striped();
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources;

use App\Modules\Subscriptions\Domain\Enums\InvoiceStatus;
use App\Modules\Subscriptions\Domain\Models\SubscriptionInvoice;
use App\Modules\Subscriptions\Filament\Resources\SubscriptionInvoiceResource\Pages\ListSubscriptionInvoices;
use App\Modules\Subscriptions\Filament\Resources\SubscriptionInvoiceResource\Pages\ViewSubscriptionInvoice;
use App\Modules\Subscriptions\Filament\Resources\SubscriptionInvoiceResource\RelationManagers\SubscriptionPaymentsRelationManager;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionInvoiceResource extends Resource
{
    protected static ?string $model = SubscriptionInvoice::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.subscriptions');
    }

    public static function getNavigationLabel(): string
    {
        return __('subscriptions::subscription.invoices');
    }

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'public_id';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vendorSubscription.vendor.business_name')
                    ->label(__('subscriptions::subscription.vendor'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('vendorSubscription.plan.plan_code')
                    ->label(__('subscriptions::subscription.plan_code'))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'free' => 'gray',
                        'silver' => 'info',
                        'gold' => 'warning',
                        'premium' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('amount_minor')
                    ->label(__('subscriptions::subscription.invoice.amount'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state->value ?? $state) {
                        InvoiceStatus::Pending->value, InvoiceStatus::Pending => 'warning',
                        InvoiceStatus::Paid->value, InvoiceStatus::Paid => 'success',
                        InvoiceStatus::Failed->value, InvoiceStatus::Failed => 'danger',
                        InvoiceStatus::Refunded->value, InvoiceStatus::Refunded => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state instanceof InvoiceStatus ? ucwords(str_replace('_', ' ', $state->value)) : $state)
                    ->sortable(),

                TextColumn::make('period_start')
                    ->label(__('subscriptions::subscription.invoice.period_start'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('period_end')
                    ->label(__('subscriptions::subscription.invoice.period_end'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('paid_at')
                    ->label(__('subscriptions::subscription.invoice.paid_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(InvoiceStatus::class),

                SelectFilter::make('vendorSubscription')
                    ->relationship('vendorSubscription', 'public_id')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            SubscriptionPaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionInvoices::route('/'),
            'view' => ViewSubscriptionInvoice::route('/{record}'),
        ];
    }

    public static function getHeaderActions(): array
    {
        return [];
    }
}

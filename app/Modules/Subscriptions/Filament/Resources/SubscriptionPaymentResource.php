<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources;

use App\Modules\Subscriptions\Domain\Models\SubscriptionPayment;
use App\Modules\Subscriptions\Filament\Resources\SubscriptionPaymentResource\Pages\ListSubscriptionPayments;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionPaymentResource extends Resource
{
    protected static ?string $model = SubscriptionPayment::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.subscriptions');
    }

    protected static ?int $navigationSort = 50;

    public static function getNavigationLabel(): string
    {
        return __('subscriptions::subscription.payments');
    }

    protected static ?string $recordTitleAttribute = 'public_id';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice.public_id')
                    ->label(__('subscriptions::subscription.invoice.id'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice.amount_minor')
                    ->label(__('subscriptions::subscription.payment.amount'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('gateway_ref')
                    ->label(__('subscriptions::subscription.payment.gateway_ref'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionPayments::route('/'),
        ];
    }

    public static function getHeaderActions(): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources\SubscriptionInvoiceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('public_id')
            ->columns([
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
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}

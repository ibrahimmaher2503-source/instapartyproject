<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources;

use App\Modules\Subscriptions\Domain\Enums\SubscriptionEventType;
use App\Modules\Subscriptions\Domain\Models\SubscriptionAuditEntry;
use App\Modules\Subscriptions\Filament\Resources\SubscriptionAuditEntryResource\Pages\ListSubscriptionAuditEntries;
use App\Modules\Subscriptions\Filament\Resources\SubscriptionAuditEntryResource\Pages\ViewSubscriptionAuditEntry;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionAuditEntryResource extends Resource
{
    protected static ?string $model = SubscriptionAuditEntry::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.subscriptions');
    }

    protected static ?int $navigationSort = 60;

    public static function getNavigationLabel(): string
    {
        return __('subscriptions::subscription.audit');
    }

    protected static ?string $recordTitleAttribute = 'public_id';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('actor_type')
                    ->label(__('subscriptions::subscription.actor'))
                    ->formatStateUsing(fn ($record) => "{$record->actor_type} #{$record->actor_id}")
                    ->sortable(),

                TextColumn::make('event_type')
                    ->badge()
                    ->color(fn ($state) => match ($state->value ?? $state) {
                        SubscriptionEventType::AdminOverrideApplied->value, SubscriptionEventType::AdminOverrideApplied => 'warning',
                        SubscriptionEventType::AdminOverrideEnded->value, SubscriptionEventType::AdminOverrideEnded => 'info',
                        SubscriptionEventType::Expired->value, SubscriptionEventType::Expired => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('vendorSubscription.vendor.business_name')
                    ->label(__('subscriptions::subscription.vendor'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('event_type')
                    ->options(SubscriptionEventType::class),

                SelectFilter::make('vendor_profile_id')
                    ->label(__('subscriptions::subscription.vendor'))
                    ->relationship('vendorProfile', 'business_name->en')
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionAuditEntries::route('/'),
            'view' => ViewSubscriptionAuditEntry::route('/{record}'),
        ];
    }

    public static function getHeaderActions(): array
    {
        return [];
    }
}

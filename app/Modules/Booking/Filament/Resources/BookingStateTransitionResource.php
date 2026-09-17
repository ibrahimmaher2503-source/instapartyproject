<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Resources\BookingStateTransitionResource\Pages\ListBookingStateTransitions;
use App\Modules\Shared\Domain\Models\StateTransition;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class BookingStateTransitionResource extends Resource
{
    protected static ?string $model = StateTransition::class;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['triggeredByUser', 'transitionable'])
            ->whereIn('transitionable_type', [
                Booking::class,
                BookingVendor::class,
                BookingItem::class,
            ]);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.operations');
    }

    protected static ?string $navigationIcon = 'heroicon-o-arrows-up-down';

    protected static ?int $navigationSort = 70;

    public static function getNavigationLabel(): string
    {
        return __('booking.nav.state_transitions');
    }

    public static function getModelLabel(): string
    {
        return __('booking.models.state_transition.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking.models.state_transition.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transitionable_type')
                    ->label(__('booking.columns.transitionable_type'))
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—')
                    ->searchable(),
                TextColumn::make('transitionable_id')
                    ->label(__('booking.columns.transitionable_id'))
                    ->getStateUsing(fn (StateTransition $record): string => $record->transitionable?->public_id
                        ?? '#'.$record->transitionable_id)
                    ->sortable(),
                TextColumn::make('from_state')
                    ->label(__('booking.columns.from_state'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state, StateTransition $record): string => self::formatState($state, $record)),
                TextColumn::make('to_state')
                    ->label(__('booking.columns.to_state'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state, StateTransition $record): string => self::formatState($state, $record)),
                TextColumn::make('trigger_kind')
                    ->label(__('booking.columns.trigger_kind'))
                    ->badge(),
                TextColumn::make('triggered_by')
                    ->label(__('booking.columns.actor'))
                    ->getStateUsing(fn (StateTransition $record): string => $record->triggeredByUser?->name
                        ?? ($record->triggered_by ? '#'.$record->triggered_by : __('booking.system_actor')))
                    ->sortable(),
                TextColumn::make('reason')
                    ->label(__('booking.columns.reason'))
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('trace_id')
                    ->label(__('booking.columns.correlation_id'))
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookingStateTransitions::route('/'),
        ];
    }

    private static function formatState(?string $state, StateTransition $record): string
    {
        if ($state === null) {
            return '—';
        }

        $translation = match ($record->transitionable_type) {
            Booking::class => __('booking.lifecycle_status.'.$state),
            BookingVendor::class => __('booking.vendor_status.'.$state),
            BookingItem::class => __('booking.item_status.'.$state),
            default => Str::headline(str_replace('_', ' ', $state)),
        };

        return is_string($translation) && str_starts_with($translation, 'booking.')
            ? Str::headline(str_replace('_', ' ', $state))
            : (string) $translation;
    }
}

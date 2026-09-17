<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Filament\Resources\CommissionResource\Pages\ListCommissions;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\ModelStates\State;

class CommissionResource extends Resource
{
    protected static ?string $model = Commission::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?int $navigationSort = 50;

    public static function getNavigationLabel(): string
    {
        return __('settlement.nav.commissions');
    }

    public static function getModelLabel(): string
    {
        return __('settlement.models.commission.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settlement.models.commission.plural');
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['bookingItem', 'payment', 'vendor']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('settlement.columns.public_id'))
                    ->copyable()
                    ->searchable(),
                TextColumn::make('vendor.business_name')
                    ->label(__('settlement.columns.vendor'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'vendor',
                        fn ($q) => $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(business_name, '$.en')) LIKE ?", ["%{$search}%"])
                    )),
                TextColumn::make('bookingItem.public_id')
                    ->label(__('settlement.columns.booking_item'))
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment.public_id')
                    ->label(__('settlement.columns.payment'))
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('product_type')
                    ->label(__('settlement.columns.product_type'))
                    ->badge()
                    ->formatStateUsing(fn (ProductType $state): string => $state->label()),
                TextColumn::make('gross_amount_minor')
                    ->label(__('settlement.columns.gross_amount'))
                    ->money('EGP', divideBy: 100),
                TextColumn::make('commission_minor')
                    ->label(__('settlement.columns.commission_amount'))
                    ->money('EGP', divideBy: 100),
                TextColumn::make('vendor_share_minor')
                    ->label(__('settlement.columns.vendor_share'))
                    ->money('EGP', divideBy: 100),
                TextColumn::make('status')
                    ->label(__('settlement.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => Str::headline(
                        $state instanceof State ? $state::getMorphClass() : (string) $state
                    )),
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
            'index' => ListCommissions::route('/'),
        ];
    }
}

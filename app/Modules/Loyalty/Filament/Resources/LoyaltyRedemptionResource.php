<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources;

use App\Modules\Loyalty\Domain\Models\LoyaltyRedemption;
use App\Modules\Loyalty\Filament\Resources\LoyaltyRedemptionResource\Pages\ListLoyaltyRedemptions;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoyaltyRedemptionResource extends Resource
{
    protected static ?string $model = LoyaltyRedemption::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.loyalty');
    }

    public static function getNavigationLabel(): string
    {
        return __('loyalty.nav.redemptions');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty.models.redemption.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty.models.redemption.plural');
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
        return parent::getEloquentQuery()->with(['user', 'vendorProfile', 'booking']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('loyalty.columns.public_id'))
                    ->copyable()
                    ->searchable()
                    ->limit(10),
                TextColumn::make('user.name')
                    ->label(__('loyalty.columns.user'))
                    ->searchable(),
                TextColumn::make('vendorProfile.business_name')
                    ->label(__('loyalty.columns.vendor'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'vendorProfile',
                        fn ($q) => $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(business_name, '$.en')) LIKE ?", ["%{$search}%"])
                    )),
                TextColumn::make('booking.public_id')
                    ->label(__('loyalty.columns.booking'))
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('points_redeemed')
                    ->label(__('loyalty.columns.points_redeemed'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('amount_minor')
                    ->label(__('loyalty.columns.amount'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('user_id')
                    ->label(__('loyalty.columns.user'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(false),
                SelectFilter::make('vendor_profile_id')
                    ->label(__('loyalty.columns.vendor'))
                    ->relationship('vendorProfile', 'business_name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => is_array($record->business_name)
                            ? ($record->business_name['en'] ?? $record->public_id)
                            : ($record->business_name ?? $record->public_id)
                    )
                    ->searchable()
                    ->preload(false),
                Filter::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->form([
                        DatePicker::make('from')
                            ->label(__('admin.common.from')),
                        DatePicker::make('until')
                            ->label(__('admin.common.until')),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoyaltyRedemptions::route('/'),
        ];
    }
}

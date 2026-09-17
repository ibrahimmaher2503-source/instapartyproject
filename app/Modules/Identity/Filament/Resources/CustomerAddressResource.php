<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Domain\Models\CustomerAddress;
use App\Modules\Identity\Filament\Resources\CustomerAddressResource\Pages\ListCustomerAddresses;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerAddressResource extends Resource
{
    protected static ?string $model = CustomerAddress::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.vendor_management');
    }

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.customer_addresses');
    }

    public static function getModelLabel(): string
    {
        return __('identity.models.customer_address.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.models.customer_address.plural');
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
        return parent::getEloquentQuery()->with(['user', 'city']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('identity.columns.id'))
                    ->copyable()
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label(__('identity.columns.user_id'))
                    ->searchable(),
                TextColumn::make('city.name')
                    ->label(__('identity.columns.city_id'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—')),
                TextColumn::make('label')
                    ->label(__('identity.columns.label'))
                    ->searchable(),
                TextColumn::make('recipient_name')
                    ->label(__('identity.columns.recipient_name'))
                    ->searchable(),
                TextColumn::make('recipient_phone_e164')
                    ->label(__('identity.columns.recipient_phone'))
                    ->searchable(),
                IconColumn::make('is_default')
                    ->label(__('identity.columns.is_default'))
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomerAddresses::route('/'),
        ];
    }
}

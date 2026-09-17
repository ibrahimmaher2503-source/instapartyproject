<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Filament\Resources;

use App\Modules\Discovery\Domain\Models\Wishlist;
use App\Modules\Discovery\Filament\Resources\WishlistResource\Pages\ListWishlists;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WishlistResource extends Resource
{
    protected static ?string $model = Wishlist::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.discovery');
    }

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('discovery.nav.wishlists');
    }

    public static function getModelLabel(): string
    {
        return __('discovery.models.wishlist.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('discovery.models.wishlist.plural');
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
        return parent::getEloquentQuery()->with(['user']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('discovery.columns.public_id'))
                    ->copyable()
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label(__('discovery.columns.user_id'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('discovery.columns.name'))
                    ->searchable(),
                TextColumn::make('items_count')
                    ->label(__('discovery.columns.items_count'))
                    ->counts('items'),
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
            'index' => ListWishlists::route('/'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorProfileResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BookingVendorsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookingVendors';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('identity.sections.bookings');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('booking.public_id')
                    ->label(__('identity.misc.booking_id'))
                    ->copyable()
                    ->limit(13),
                TextColumn::make('sub_status')
                    ->badge()
                    ->label(__('booking.sub_status')),
                TextColumn::make('booking.lifecycle_status')
                    ->badge()
                    ->label(__('booking.lifecycle_status')),
                TextColumn::make('vendor_total_minor')
                    ->money('EGP', divideBy: 100)
                    ->label(__('booking.vendor_total')),
                TextColumn::make('booking.created_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('shared.created_at')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canEdit(Model $record): bool
    {
        return false;
    }

    public function canDelete(Model $record): bool
    {
        return false;
    }
}

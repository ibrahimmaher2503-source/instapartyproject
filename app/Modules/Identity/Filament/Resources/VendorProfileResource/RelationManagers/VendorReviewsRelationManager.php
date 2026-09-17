<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorProfileResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class VendorReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'vendorReviews';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('identity.sections.reviews');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('identity.misc.id_short'))
                    ->copyable()
                    ->limit(13),
                TextColumn::make('rating')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 4 => 'success',
                        $state === 3 => 'warning',
                        default => 'danger',
                    })
                    ->label(__('reviews.rating')),
                TextColumn::make('comment')
                    ->limit(60)
                    ->wrap()
                    ->label(__('reviews.comment')),
                TextColumn::make('status')
                    ->badge()
                    ->label(__('reviews.status')),
                TextColumn::make('created_at')
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

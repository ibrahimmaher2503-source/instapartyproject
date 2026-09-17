<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorProfileResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('identity::identity.sections.documents');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('identity::identity.columns.public_id'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('doc_type')
                    ->badge()
                    ->label(__('identity::identity.columns.doc_type')),
                TextColumn::make('file_name')
                    ->label(__('identity::identity.columns.file_name'))
                    ->limit(40),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state->value ?? $state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    })
                    ->label(__('identity::identity.columns.status')),
                TextColumn::make('expires_at')
                    ->date()
                    ->label(__('identity::identity.fields.expires_at')),
                TextColumn::make('is_critical')
                    ->boolean()
                    ->label(__('identity::identity.fields.is_critical')),
                TextColumn::make('reviewer.name')
                    ->label(__('identity::identity.columns.reviewed_by'))
                    ->placeholder('-'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('shared.updated_at')),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([]);
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canDelete(Model $record): bool
    {
        return false;
    }
}

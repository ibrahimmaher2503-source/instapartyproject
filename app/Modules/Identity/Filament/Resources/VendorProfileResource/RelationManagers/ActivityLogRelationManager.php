<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorProfileResource\RelationManagers;

use App\Modules\Identity\Application\Support\MaskBankData;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ActivityLogRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('identity.sections.activity');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label(__('identity.activity.description'))
                    ->searchable(),
                TextColumn::make('causer.name')
                    ->label(__('identity.activity.caused_by')),
                TextColumn::make('properties')
                    ->label(__('identity.activity.properties'))
                    ->formatStateUsing(fn ($state): string => $this->formatProperties($state))
                    ->limit(80),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('shared.created_at')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private function formatProperties(mixed $state): string
    {
        $properties = $state instanceof Collection
            ? $state->toArray()
            : (is_array($state) ? $state : []);

        $properties = $this->maskSensitiveProperties($properties);

        return json_encode($properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '—';
    }

    /** @param array<string, mixed> $properties */
    private function maskSensitiveProperties(array $properties): array
    {
        foreach (MaskBankData::FIELDS as $field) {
            if (array_key_exists($field, $properties)) {
                $properties[$field] = MaskBankData::forField($field, $properties[$field]);
            }
        }

        foreach (['old', 'new'] as $section) {
            if (is_array($properties[$section] ?? null)) {
                /** @var array<string, mixed> $sectionProperties */
                $sectionProperties = $properties[$section];
                $properties[$section] = $this->maskSensitiveProperties($sectionProperties);
            }
        }

        return $properties;
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

<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ActivitylogResource\Pages\ListActivitylog;
use App\Filament\Resources\ActivitylogResource\Pages\ViewActivitylog;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Component as Livewire;
use Rmsramos\Activitylog\RelationManagers\ActivitylogRelationManager;
use Rmsramos\Activitylog\Resources\ActivitylogResource as BaseActivitylogResource;

class ActivitylogResource extends BaseActivitylogResource
{
    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->actions([ViewAction::make()])
            ->emptyStateIcon('heroicon-o-shield-check')
            ->emptyStateHeading(__('admin.activity_log.empty_heading'))
            ->emptyStateDescription(__('admin.activity_log.empty_description'));
    }

    public static function getLogNameColumnComponent(): Column
    {
        return TextColumn::make('log_name')
            ->label(self::translate('activitylog::tables.columns.log_name.label'))
            ->formatStateUsing(fn ($state): string => blank($state) || $state === 'default'
                ? self::legacyLabel()
                : Str::headline((string) $state))
            ->searchable()
            ->sortable()
            ->badge();
    }

    public static function getEventColumnComponent(): Column
    {
        return TextColumn::make('event')
            ->label(self::translate('activitylog::tables.columns.event.label'))
            ->formatStateUsing(function ($state): string {
                $knownEvents = ['created', 'updated', 'deleted', 'restored'];

                if (! is_string($state) || ! in_array($state, $knownEvents, true)) {
                    return self::legacyLabel();
                }

                return self::translate('activitylog::action.event.'.$state);
            })
            ->badge()
            ->color(fn (?string $state): string => match ($state) {
                'created' => 'success',
                'updated' => 'warning',
                'deleted' => 'danger',
                'restored' => 'info',
                default => 'gray',
            })
            ->searchable()
            ->sortable();
    }

    public static function getSubjectTypeColumnComponent(): Column
    {
        return TextColumn::make('subject_type')
            ->label(self::translate('activitylog::tables.columns.subject_type.label'))
            ->formatStateUsing(function ($state, Model $record): string {
                if (! is_string($state) || blank($state)) {
                    return self::legacyLabel();
                }

                $subjectLabel = Str::of($state)->afterLast('\\')->headline();
                $subject = $record->getRelationValue('subject');
                $publicId = $subject instanceof Model ? $subject->getAttribute('public_id') : null;

                return is_string($publicId) && filled($publicId)
                    ? "{$subjectLabel} · {$publicId}"
                    : "{$subjectLabel} · ".self::legacyLabel();
            })
            ->searchable()
            ->hidden(fn (Livewire $livewire): bool => $livewire instanceof ActivitylogRelationManager);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivitylog::route('/'),
            'view' => ViewActivitylog::route('/{record}'),
        ];
    }

    private static function legacyLabel(): string
    {
        return self::translate('shared::admin.activity_log.legacy_unknown');
    }

    private static function translate(string $key): string
    {
        $translation = __($key);

        return is_string($translation) ? $translation : $key;
    }
}

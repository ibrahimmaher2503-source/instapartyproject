<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AdminActivityLogResource\Pages\ListAdminActivityLogs;
use App\Filament\Resources\AdminActivityLogResource\Pages\ViewAdminActivityLog;
use App\Modules\Shared\Filament\ActivityLog\ActivityLogPresenter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Rmsramos\Activitylog\Resources\ActivitylogResource as PackageActivitylogResource;
use Spatie\Activitylog\Models\Activity;

class AdminActivityLogResource extends PackageActivitylogResource
{
    protected static ?string $slug = 'activitylogs';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_activitylog') === true;
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_activitylog') === true;
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
        return parent::getEloquentQuery()->with(['subject', 'causer']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Placeholder::make('record_label')
                ->label(__('shared::admin-feedback.audit_record'))
                ->content(fn (?Activity $record): string => $record ? ActivityLogPresenter::recordLabel($record) : '—'),
            Placeholder::make('causer.name')
                ->label(__('shared::admin-feedback.audit_actor'))
                ->content(fn (?Activity $record): string => $record?->causer?->name ?: __('shared::admin-feedback.value_unavailable')),
            Placeholder::make('description')
                ->label(__('shared::admin-feedback.audit_description'))
                ->content(fn (?Activity $record): string => $record?->description ?: '—'),
            Placeholder::make('created_at')
                ->label(__('shared::admin-feedback.audit_created_at'))
                ->content(fn (?Activity $record): string => $record?->created_at?->toDateTimeString() ?: '—'),
            Textarea::make('safe_changes')
                ->label(__('shared::admin-feedback.audit_safe_changes'))
                ->state(fn (?Activity $record): string => $record ? ActivityLogPresenter::safeChangesSummary($record) : '')
                ->rows(10)
                ->disabled()
                ->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('log_name')
                    ->label(__('activitylog::tables.columns.log_name.label'))
                    ->formatStateUsing(fn ($state): string => is_string($state) && $state !== '' ? str($state)->headline()->toString() : '—')
                    ->badge()
                    ->sortable(),
                TextColumn::make('event')
                    ->label(__('activitylog::tables.columns.event.label'))
                    ->formatStateUsing(fn (?string $state): string => $state ? __('activitylog::action.event.'.$state) : '—')
                    ->badge()
                    ->sortable(),
                TextColumn::make('record_label')
                    ->label(__('shared::admin-feedback.audit_record'))
                    ->state(fn (Activity $record): string => ActivityLogPresenter::recordLabel($record))
                    ->url(fn (Activity $record): ?string => ActivityLogPresenter::recordUrl($record))
                    ->limit(70)
                    ->wrap(),
                TextColumn::make('causer.name')
                    ->label(__('shared::admin-feedback.audit_actor'))
                    ->default(__('shared::admin-feedback.value_unavailable')),
                TextColumn::make('safe_changes')
                    ->label(__('shared::admin-feedback.audit_safe_changes'))
                    ->state(fn (Activity $record): string => ActivityLogPresenter::safeChangesSummary($record))
                    ->limit(120)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label(__('activitylog::tables.columns.created_at.label'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')->options(static::getModel()::query()->whereNotNull('event')->distinct()->orderBy('event')->pluck('event', 'event')->all()),
                SelectFilter::make('log_name')->options(static::getModel()::query()->whereNotNull('log_name')->distinct()->orderBy('log_name')->pluck('log_name', 'log_name')->all()),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label(__('shared::admin-feedback.audit_from'))->native(false),
                        DatePicker::make('until')->label(__('shared::admin-feedback.audit_until'))->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['from'] ?? null), fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $data['from']))
                        ->when(filled($data['until'] ?? null), fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $data['until']))),
            ])
            ->actions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminActivityLogs::route('/'),
            'view' => ViewAdminActivityLog::route('/{record}'),
        ];
    }
}

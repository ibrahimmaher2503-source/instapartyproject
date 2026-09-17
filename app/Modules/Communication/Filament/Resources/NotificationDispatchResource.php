<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources;

use App\Modules\Communication\Application\Actions\RetryFailedDispatchAction;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Filament\Resources\NotificationDispatchResource\Pages\ListNotificationDispatches;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NotificationDispatchResource extends Resource
{
    protected static ?string $model = NotificationDispatch::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.communication');
    }

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('communication.nav.dispatches');
    }

    public static function getModelLabel(): string
    {
        return __('communication.models.notification_dispatch.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('communication.models.notification_dispatch.plural');
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
        return parent::getEloquentQuery()->with(['template']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('communication.columns.public_id'))
                    ->copyable()
                    ->searchable(),
                TextColumn::make('template.event_key')
                    ->label(__('communication.columns.template'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('channel')
                    ->label(__('communication.columns.channel'))
                    ->badge()
                    ->formatStateUsing(fn (NotificationChannel $state): string => $state->label()),
                TextColumn::make('status')
                    ->label(__('communication.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (DispatchStatus $state): string => $state->label()),
                TextColumn::make('locale')
                    ->label(__('communication.columns.locale'))
                    ->badge(),
                IconColumn::make('is_test')
                    ->label(__('communication.dispatch.is_test'))
                    ->boolean()
                    ->trueIcon('heroicon-o-beaker')
                    ->trueColor('info'),
                TextColumn::make('attempt_count')
                    ->label(__('communication.dispatch.attempts')),
                TextColumn::make('provider')
                    ->label(__('communication.columns.provider')),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('communication.columns.status'))
                    ->options(
                        collect(DispatchStatus::cases())
                            ->mapWithKeys(fn (DispatchStatus $case): array => [$case->value => $case->label()])
                            ->all()
                    ),
                TernaryFilter::make('is_test')
                    ->label(__('communication.dispatch.filter_test_only')),
                SelectFilter::make('provider_name')
                    ->label(__('communication.columns.provider'))
                    ->options(fn (): array => NotificationDispatch::query()
                        ->whereNotNull('provider_name')
                        ->distinct()
                        ->orderBy('provider_name')
                        ->pluck('provider_name', 'provider_name')
                        ->all()
                    ),
            ])
            ->actions([
                Action::make('retry')
                    ->label(__('communication.provider_health.retry'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (NotificationDispatch $record): bool => $record->is_retryable && auth()->user()?->can('retry_notification_dispatch')
                    )
                    ->action(function (NotificationDispatch $record) {
                        app(RetryFailedDispatchAction::class)
                            ->execute($record, auth()->id());

                        Notification::make()
                            ->title(__('communication.provider_health.notifications.dispatch_retried', [
                                'attempt' => $record->fresh()->attempt_count,
                            ]))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationDispatches::route('/'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources;

use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Filament\Resources\GatewayWebhookLogResource\Pages\ListGatewayWebhookLogs;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class GatewayWebhookLogResource extends Resource
{
    protected static ?string $model = GatewayWebhookLog::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.payments');
    }

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('payments.navigation.webhook_logs');
    }

    public static function getModelLabel(): string
    {
        return __('payments.models.webhook_log.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payments.models.webhook_log.plural');
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

    public static function signatureStatus(?bool $state): string
    {
        $status = match ($state) {
            true => 'valid',
            false => 'invalid',
            default => 'not_checked',
        };

        return __('payments.signature_status.'.$status);
    }

    public static function processingStatus(GatewayWebhookLog $record): string
    {
        $status = match (true) {
            $record->signature_valid === false => 'rejected',
            in_array($record->processing_error, ['duplicate_idempotent_replay', 'duplicate_refund_idempotent_replay'], true) => 'duplicate',
            $record->processed_at !== null => 'processed',
            $record->processing_error !== null => 'failed',
            default => 'pending',
        };

        return __('payments.processing_status.'.$status);
    }

    public static function eventTypeLabel(?string $state): string
    {
        $value = trim((string) $state);
        if ($value === '') {
            return __('payments.event_types.unknown');
        }

        $value = (string) preg_replace_callback(
            '/(?:(?:[a-z]\s+){2,}[a-z])(?=\s|$)/i',
            static fn (array $matches): string => str_replace(' ', '', $matches[0]),
            $value,
        );
        $key = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $value));
        $key = trim($key, '_');
        $translationKey = 'payments.event_types.'.$key;
        $translated = __($translationKey);

        return $translated === $translationKey ? Str::headline(str_replace('_', ' ', $key)) : $translated;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('gateway')
                    ->label(__('payments.columns.gateway'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('event_type')
                    ->label(__('payments.columns.event_type'))
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): string => self::eventTypeLabel($state)),
                TextColumn::make('signature_valid')
                    ->label(__('payments.columns.signature_valid'))
                    ->badge()
                    ->formatStateUsing(fn (?bool $state): string => self::signatureStatus($state))
                    ->color(fn (?bool $state): string => match ($state) {
                        true => 'success',
                        false => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('processing_status')
                    ->label(__('payments.columns.processing_status'))
                    ->badge()
                    ->state(fn (GatewayWebhookLog $record): string => self::processingStatus($record))
                    ->color(fn (string $state): string => match ($state) {
                        __('payments.processing_status.processed') => 'success',
                        __('payments.processing_status.duplicate') => 'info',
                        __('payments.processing_status.rejected'), __('payments.processing_status.failed') => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('processed_at')
                    ->label(__('payments.columns.processed_at'))
                    ->dateTime()
                    ->sortable(),
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
            'index' => ListGatewayWebhookLogs::route('/'),
        ];
    }
}

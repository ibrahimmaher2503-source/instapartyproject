<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources;

use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Filament\Resources\RefundResource\Pages\ListRefunds;
use App\Modules\Payments\Filament\Resources\RefundResource\Pages\ViewRefund;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Filament\Infolists\Components\AuditTimelineSection;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.payments');
    }

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('payments.navigation.refunds');
    }

    public static function getModelLabel(): string
    {
        return __('payments.models.refund.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payments.models.refund.plural');
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('payments.refund.columns.public_id'))
                    ->copyable(),
                TextColumn::make('booking.public_id')
                    ->label(__('payments.refund.columns.booking_reference'))
                    ->placeholder(__('payments.identity.legacy_unknown')),
                TextColumn::make('amount_minor')
                    ->label(__('payments.refund.columns.amount'))
                    ->money('EGP', divideBy: 100),
                TextColumn::make('reason_code')
                    ->label(__('payments.refund.columns.reason_code'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('payments.refund.columns.status'))
                    ->badge(),
                TextColumn::make('created_at')
                    ->label(__('payments.refund.columns.created_at'))
                    ->dateTime(),
            ])
            ->actions([ViewAction::make()]);
    }

    public static function infolist(Infolist $schema): Infolist
    {
        return $schema->components([
            AuditTimelineSection::make()
                ->audience(TimelineAudience::Admin)
                ->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRefunds::route('/'),
            'view' => ViewRefund::route('/{record}'),
        ];
    }
}

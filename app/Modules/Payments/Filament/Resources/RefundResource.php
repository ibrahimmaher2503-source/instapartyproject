<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources;

use App\Modules\Shared\Filament\Tables\OperationalExport;
use App\Modules\Shared\Filament\Tables\OperationalDateFilter;

use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Filament\Resources\RefundResource\Pages\ListRefunds;
use App\Modules\Payments\Filament\Resources\RefundResource\Pages\ViewRefund;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Filament\Infolists\Components\AuditTimelineSection;
use App\Modules\Shared\Filament\Tables\ReferenceSearch;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.payments');
    }

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?int $navigationSort = 20;

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
            ->headerActions([OperationalExport::make(static::class, ['public_id', 'booking.public_id', 'status', 'amount_minor', 'amount_currency', 'created_at'])])
            ->filters([OperationalDateFilter::make()])
            ->columns([
                TextColumn::make('public_id')
                    ->searchable(query: fn (Builder $query, string $search): Builder => ReferenceSearch::apply($query, $search, [
                        '' => ['public_id', 'gateway_ref'],
                        'payment' => ['public_id'],
                        'booking' => ['public_id', 'reference_no'],
                        'booking.customer' => ['public_id', 'name', 'email', 'phone_e164'],
                        'booking.vendors.vendor' => ['public_id', 'business_name->en', 'business_name->ar'],
                        'booking.vendors.vendor.user' => ['public_id', 'name', 'email', 'phone_e164'],
                    ]))
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

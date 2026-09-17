<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources;

use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\PaymentState;
use App\Modules\Payments\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Modules\Payments\Filament\Resources\PaymentResource\Pages\ViewPayment;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Filament\Infolists\Components\AuditTimelineSection;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $recordTitleAttribute = 'public_id';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.payments');
    }

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('payments.navigation.payments');
    }

    public static function getModelLabel(): string
    {
        return __('payments.models.payment.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payments.models.payment.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['booking']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'booking_manager', 'super_admin']) === true;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'booking_manager', 'super_admin']) === true;
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
                    ->label(__('payments.columns.public_id'))
                    ->copyable()
                    ->searchable(),
                TextColumn::make('booking.reference_no')
                    ->label(__('payments.columns.booking'))
                    ->searchable()
                    ->sortable()
                    ->default(fn (Payment $record): string => '#'.$record->booking_id),
                TextColumn::make('gateway')
                    ->label(__('payments.columns.gateway'))
                    ->searchable(),
                TextColumn::make('amount_minor')
                    ->label(__('payments.columns.amount'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),
                TextColumn::make('method')
                    ->label(__('payments.columns.method'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentMethod $state): string => $state->getLabel()),
                TextColumn::make('status')
                    ->label(__('payments.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => PaymentState::label(
                        $state instanceof PaymentState ? $state->getValue() : (string) $state,
                        app()->getLocale(),
                    ) ?? (string) $state),
                TextColumn::make('captured_at')
                    ->label(__('payments.columns.captured_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('payments.columns.status'))
                    ->options([
                        'pending' => __('payments.status.pending'),
                        'authorized' => __('payments.status.authorized'),
                        'captured' => __('payments.status.captured'),
                        'failed' => __('payments.status.failed'),
                        'voided' => __('payments.status.voided'),
                        'abandoned' => __('payments.status.abandoned'),
                        'partially_refunded' => __('payments.status.partially_refunded'),
                        'refunded' => __('payments.status.refunded'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'])
                            ? $query->where('status', $data['value'])
                            : $query
                    ),
                SelectFilter::make('gateway')
                    ->label(__('payments.columns.gateway'))
                    ->options(['paymob' => 'Paymob']),
                Filter::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->form([
                        DatePicker::make('from')->label(__('admin.filters.date_from')),
                        DatePicker::make('until')->label(__('admin.filters.date_until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                            ->when($data['until'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->defaultSort('created_at', 'desc');
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
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}

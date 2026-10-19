<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources;

use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\PaymentState;
use App\Modules\Payments\Filament\Actions\RefundPaymentAction;
use App\Modules\Payments\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Modules\Payments\Filament\Resources\PaymentResource\Pages\ViewPayment;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Filament\Infolists\Components\AuditTimelineSection;
use App\Modules\Shared\Filament\Tables\OperationalDateFilter;
use App\Modules\Shared\Filament\Tables\OperationalExport;
use App\Modules\Shared\Filament\Tables\ReferenceSearch;
use Brick\Money\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
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
            ->headerActions([OperationalExport::make(static::class, ['public_id', 'booking.public_id', 'status', 'amount_minor', 'amount_currency', 'created_at'])])
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('payments.columns.public_id'))
                    ->copyable()
                    ->searchable(query: fn (Builder $query, string $search): Builder => ReferenceSearch::apply($query, $search, [
                        '' => ['public_id', 'gateway_ref'],
                        'booking' => ['public_id', 'reference_no'],
                        'booking.customer' => ['public_id', 'name', 'email', 'phone_e164'],
                        'booking.vendors.vendor' => ['public_id', 'business_name->en', 'business_name->ar'],
                        'booking.vendors.vendor.user' => ['public_id', 'name', 'email', 'phone_e164'],
                    ])),
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
                RefundPaymentAction::make(),
            ])
            ->filters([
                OperationalDateFilter::make('created_at'),
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
                Filter::make('amount')
                    ->label(__('payments.columns.amount'))
                    ->form([
                        TextInput::make('min')->numeric()->minValue(0)->maxLength(21)
                            ->rules(['nullable', 'regex:/^\d{1,18}(?:\.\d{1,2})?$/D'])
                            ->label(__('payments.filters.amount_min')),
                        TextInput::make('max')->numeric()->minValue(0)->maxLength(21)
                            ->rules(['nullable', 'regex:/^\d{1,18}(?:\.\d{1,2})?$/D'])
                            ->label(__('payments.filters.amount_max')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $toMinor = static function (mixed $amount): ?string {
                            if ((! is_string($amount)) && (! is_int($amount))) {
                                return null;
                            }

                            $amount = trim((string) $amount);

                            return preg_match('/^\d{1,18}(?:\.\d{1,2})?$/D', $amount)
                                ? (string) Money::of($amount, 'EGP')->getMinorAmount()
                                : null;
                        };
                        $minimum = $toMinor($data['min'] ?? null);
                        $maximum = $toMinor($data['max'] ?? null);

                        return $query
                            ->when($minimum !== null || $maximum !== null, fn (Builder $query): Builder => $query->where('amount_currency', 'EGP'))
                            ->when($minimum !== null, fn (Builder $query): Builder => $query->where('amount_minor', '>=', $minimum))
                            ->when($maximum !== null, fn (Builder $query): Builder => $query->where('amount_minor', '<=', $maximum));
                    }),
                SelectFilter::make('vendor_id')
                    ->label(__('payments.filters.vendor'))
                    ->relationship('booking.vendors.vendor', 'business_name->'.app()->getLocale())
                    ->searchable(),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $schema): Infolist
    {
        return $schema->components([
            Section::make(__('payments::payments.sections.payment_details'))
                ->schema([
                    TextEntry::make('public_id')
                        ->label(__('payments::payments.columns.public_id'))
                        ->copyable(),
                    TextEntry::make('amount_minor')
                        ->label(__('payments::payments.columns.amount'))
                        ->formatStateUsing(fn (int $state, Payment $record): string => self::formatMoney($state, $record->amount_currency)),
                    TextEntry::make('status')
                        ->label(__('payments::payments.columns.status'))
                        ->formatStateUsing(fn (mixed $state): string => PaymentState::label(
                            $state instanceof PaymentState ? $state->getValue() : (string) $state,
                            app()->getLocale(),
                        ) ?? (string) $state),
                    TextEntry::make('gateway')
                        ->label(__('payments::payments.columns.gateway')),
                    TextEntry::make('gateway_ref')
                        ->label(__('payments::payments.gateway_reference'))
                        ->placeholder('—')
                        ->copyable(),
                    TextEntry::make('method')
                        ->label(__('payments::payments.columns.method'))
                        ->formatStateUsing(fn (mixed $state): string => $state instanceof PaymentMethod ? $state->getLabel() : (string) $state),
                    TextEntry::make('captured_at')
                        ->label(__('payments::payments.columns.captured_at'))
                        ->dateTime()
                        ->placeholder('—'),
                    TextEntry::make('booking.reference_no')
                        ->label(__('payments::payments.columns.booking'))
                        ->url(fn (Payment $record): ?string => $record->booking !== null
                            && auth()->user()?->can('view', $record->booking) === true
                            ? route('filament.admin.resources.bookings.view', ['record' => $record->booking->public_id])
                            : null)
                        ->placeholder('—'),
                    TextEntry::make('booking.public_id')
                        ->label(__('payments::payments.booking_public_id'))
                        ->copyable()
                        ->placeholder('—'),
                    TextEntry::make('booking.customer.name')
                        ->label(__('payments::payments.customer'))
                        ->placeholder('—'),
                    TextEntry::make('booking.total_vat_minor')
                        ->label(__('payments::payments.tax_collected'))
                        ->formatStateUsing(fn (?int $state, Payment $record): string => $record->booking === null
                            ? '—'
                            : self::formatMoney($state ?? 0, $record->booking->total_currency ?: $record->amount_currency))
                        ->placeholder('—'),
                ])
                ->columns(2)
                ->columnSpanFull(),
            RepeatableEntry::make('booking.items')
                ->label(__('payments::payments.sections.tax_breakdown'))
                ->schema([
                    TextEntry::make('name_snapshot')
                        ->label(__('payments::payments.booking_item'))
                        ->formatStateUsing(fn (mixed $state): string => is_array($state)
                            ? (string) ($state[app()->getLocale()] ?? $state['en'] ?? reset($state) ?: '—')
                            : (string) $state),
                    TextEntry::make('vat_rate_bps')
                        ->label(__('payments::payments.vat_rate_bps'))
                        ->formatStateUsing(fn (int $state): string => $state.' bps'),
                    TextEntry::make('vat_amount_minor')
                        ->label(__('payments::payments.vat_amount'))
                        ->formatStateUsing(fn (int $state, $record): string => self::formatMoney($state, $record->vat_amount_currency ?: 'EGP')),
                ])
                ->columns(3)
                ->columnSpanFull(),
            AuditTimelineSection::make()
                ->audience(TimelineAudience::Admin)
                ->columnSpanFull(),
        ]);
    }

    private static function formatMoney(int $minor, string $currency): string
    {
        return Money::ofMinor($minor, $currency)->formatTo(app()->getLocale());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}

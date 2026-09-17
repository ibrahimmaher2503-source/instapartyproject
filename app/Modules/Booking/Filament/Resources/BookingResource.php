<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources;

use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ActiveState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\BookingLifecycleState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CompletedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ConfirmedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\SubmittedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\BookingPaymentState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\PaidState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\PartiallyRefundedState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\PartialState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\RefundedState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\RefundPendingState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use App\Modules\Booking\Filament\Resources\BookingResource\Pages\CreateBooking;
use App\Modules\Booking\Filament\Resources\BookingResource\Pages\ListBookings;
use App\Modules\Booking\Filament\Resources\BookingResource\Pages\ViewBooking;
use App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers\BookingAddressesRelationManager;
use App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers\BookingItemsRelationManager;
use App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers\BookingSnapshotsRelationManager;
use App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers\BookingStateTransitionsRelationManager;
use App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers\BookingVendorsRelationManager;
use App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers\PaymentsRelationManager;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Filament\Infolists\Components\AuditTimelineSection;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $recordTitleAttribute = 'reference_no';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.operations');
    }

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('booking.nav.bookings');
    }

    public static function getModelLabel(): string
    {
        return __('booking.models.booking.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking.models.booking.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer', 'occasion', 'vendors.vendor'])
            ->withCount(['vendors', 'items', 'snapshots']);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'booking_manager', 'super_admin']) === true;
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
                    ->label(__('booking.columns.public_id'))
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference_no')
                    ->label(__('booking.columns.reference_no'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label(__('booking.columns.customer_id'))
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(fn (Booking $record): string => self::resolveCustomerName($record))
                    ->description(fn (Booking $record): ?string => self::resolveCustomerPhone($record))
                    ->tooltip(fn (Booking $record): ?string => self::resolveCustomerPhone($record)),
                TextColumn::make('occasion_id')
                    ->label(__('booking.columns.occasion_id'))
                    ->sortable()
                    ->getStateUsing(fn (Booking $record): string => self::resolveTranslatedLabel($record->occasion, 'name') ?? '#'.$record->occasion_id),
                TextColumn::make('lifecycle_status')
                    ->label(__('booking.columns.lifecycle_status'))
                    ->badge()
                    ->color(fn (mixed $state): string => match (true) {
                        $state instanceof DraftState => 'gray',
                        $state instanceof SubmittedState,
                        $state instanceof VendorReviewState,
                        $state instanceof CustomerReviewState => 'warning',
                        $state instanceof ConfirmedState,
                        $state instanceof ActiveState => 'info',
                        $state instanceof CompletedState => 'success',
                        $state instanceof CancelledState => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof BookingLifecycleState
                        ? __('booking.lifecycle_status.'.$state->getValue())
                        : (string) $state),
                TextColumn::make('payment_status')
                    ->label(__('booking.columns.payment_status'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof BookingPaymentState
                        ? __('booking.payment_status.'.$state->getValue())
                        : (string) $state),
                TextColumn::make('fulfillment_status')
                    ->label(__('booking.columns.fulfillment_status'))
                    ->badge()
                    ->formatStateUsing(fn (FulfillmentStatus $state): string => __('booking.fulfillment_status.'.$state->value)),
                TextColumn::make('total_minor')
                    ->label(__('booking.columns.total'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->label(__('booking.columns.submitted_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(fn (Booking $record): string => static::getUrl('view', ['record' => $record]))
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $schema): Infolist
    {
        return $schema
            ->columns(12)
            ->components([
                // ── KPI strip (left half) ─────────────────────────────────
                Section::make(__('booking.sections.kpis'))
                    ->icon('heroicon-o-presentation-chart-line')
                    ->columns(2)
                    ->columnSpan(6)
                    ->schema([
                        TextEntry::make('reference_no')
                            ->label(__('booking.columns.reference_no'))
                            ->icon('heroicon-m-hashtag')
                            ->size('lg')
                            ->weight(FontWeight::Bold)
                            ->copyable(),
                        TextEntry::make('total_minor')
                            ->label(__('booking.columns.total'))
                            ->money('EGP', divideBy: 100)
                            ->icon('heroicon-m-banknotes')
                            ->size('lg')
                            ->weight(FontWeight::Bold)
                            ->color('success'),
                        TextEntry::make('amount_paid_minor')
                            ->label(__('booking.columns.amount_paid'))
                            ->money('EGP', divideBy: 100)
                            ->icon('heroicon-m-credit-card')
                            ->size('lg')
                            ->weight(FontWeight::SemiBold)
                            ->color('info'),
                        TextEntry::make('guest_count')
                            ->label(__('booking.columns.guest_count'))
                            ->icon('heroicon-m-user-group')
                            ->size('lg')
                            ->weight(FontWeight::SemiBold),
                    ]),

                // ── Status strip (right half) ─────────────────────────────
                Section::make(__('booking.sections.status'))
                    ->icon('heroicon-o-flag')
                    ->columns(1)
                    ->columnSpan(6)
                    ->schema([
                        TextEntry::make('lifecycle_status')
                            ->label(__('booking.columns.lifecycle_status'))
                            ->badge()
                            ->icon('heroicon-m-flag')
                            ->color(fn (mixed $state): string => match (true) {
                                $state instanceof DraftState => 'gray',
                                $state instanceof SubmittedState,
                                $state instanceof VendorReviewState,
                                $state instanceof CustomerReviewState => 'warning',
                                $state instanceof ConfirmedState,
                                $state instanceof ActiveState => 'info',
                                $state instanceof CompletedState => 'success',
                                $state instanceof CancelledState => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (mixed $state): string => $state instanceof BookingLifecycleState
                                ? __('booking.lifecycle_status.'.$state->getValue())
                                : (string) $state),
                        TextEntry::make('payment_status')
                            ->label(__('booking.columns.payment_status'))
                            ->badge()
                            ->icon('heroicon-m-credit-card')
                            ->color(fn (mixed $state): string => match (true) {
                                $state instanceof UnpaidState,
                                $state instanceof PartialState => 'warning',
                                $state instanceof PaidState => 'success',
                                $state instanceof RefundPendingState => 'info',
                                $state instanceof PartiallyRefundedState => 'gray',
                                $state instanceof RefundedState => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (mixed $state): string => $state instanceof BookingPaymentState
                                ? __('booking.payment_status.'.$state->getValue())
                                : (string) $state),
                        TextEntry::make('fulfillment_status')
                            ->label(__('booking.columns.fulfillment_status'))
                            ->badge()
                            ->icon('heroicon-m-truck')
                            ->color(fn (FulfillmentStatus $state): string => match ($state) {
                                FulfillmentStatus::NotStarted => 'warning',
                                FulfillmentStatus::InProgress, FulfillmentStatus::PartiallyCompleted => 'info',
                                FulfillmentStatus::Completed => 'success',
                                FulfillmentStatus::Failed => 'danger',
                            })
                            ->formatStateUsing(fn (FulfillmentStatus $state): string => __('booking.fulfillment_status.'.$state->value)),
                    ]),

                // ── Customer (left half) ─────────────────────────────────
                Section::make(__('booking.sections.customer'))
                    ->icon('heroicon-o-user')
                    ->collapsible()
                    ->columnSpan(6)
                    ->schema([
                        TextEntry::make('customer.name')
                            ->label(__('booking.columns.customer_id'))
                            ->icon('heroicon-m-user')
                            ->size('lg')
                            ->weight(FontWeight::SemiBold)
                            ->getStateUsing(fn (Booking $record): string => self::resolveCustomerName($record)),
                        TextEntry::make('customer.phone_e164')
                            ->label(__('booking.columns.customer_phone'))
                            ->icon('heroicon-m-phone')
                            ->copyable()
                            ->getStateUsing(fn (Booking $record): ?string => self::resolveCustomerPhone($record)),
                        TextEntry::make('public_id')
                            ->label(__('booking.columns.public_id'))
                            ->icon('heroicon-m-key')
                            ->fontFamily(FontFamily::Mono)
                            ->copyable(),
                    ]),

                // ── Event (right half) ───────────────────────────────────
                Section::make(__('booking.sections.event'))
                    ->icon('heroicon-o-calendar-days')
                    ->collapsible()
                    ->columns(2)
                    ->columnSpan(6)
                    ->schema([
                        TextEntry::make('occasion_id')
                            ->label(__('booking.columns.occasion_id'))
                            ->icon('heroicon-m-sparkles')
                            ->size('lg')
                            ->weight(FontWeight::SemiBold)
                            ->columnSpanFull()
                            ->getStateUsing(fn (Booking $record): string => self::resolveTranslatedLabel($record->occasion, 'name') ?? '#'.$record->occasion_id),
                        TextEntry::make('event_starts_at')
                            ->label(__('booking.columns.event_starts_at'))
                            ->icon('heroicon-m-calendar')
                            ->dateTime(),
                        TextEntry::make('event_ends_at')
                            ->label(__('booking.columns.event_ends_at'))
                            ->icon('heroicon-m-calendar-days')
                            ->dateTime(),
                        TextEntry::make('submitted_at')
                            ->label(__('booking.columns.submitted_at'))
                            ->icon('heroicon-m-paper-airplane')
                            ->dateTime(),
                        TextEntry::make('created_at')
                            ->label(__('admin.common.created_at'))
                            ->icon('heroicon-m-clock')
                            ->dateTime(),
                    ]),

                // ── Audit timeline (full width) ───────────────────────────
                AuditTimelineSection::make()
                    ->audience(TimelineAudience::Admin)
                    ->columnSpan(12),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            BookingVendorsRelationManager::class,
            BookingItemsRelationManager::class,
            BookingAddressesRelationManager::class,
            PaymentsRelationManager::class,
            BookingSnapshotsRelationManager::class,
            BookingStateTransitionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
            'create' => CreateBooking::route('/create'),
            'view' => ViewBooking::route('/{record}'),
        ];
    }

    private static function resolveCustomerName(Booking $record): string
    {
        return $record->customer?->name ?? '#'.$record->customer_id;
    }

    private static function resolveCustomerPhone(Booking $record): ?string
    {
        return $record->customer?->phone_e164;
    }

    private static function resolveTranslatedLabel(?object $record, string $attribute): ?string
    {
        if ($record === null || ! method_exists($record, 'getTranslation')) {
            return null;
        }

        $locale = app()->getLocale();
        $value = $record->getTranslation($attribute, $locale, false);

        if (! is_string($value) || $value === '') {
            $value = $record->getTranslation($attribute, 'en', false);
        }

        while (is_array($value)) {
            $value = $value[$locale] ?? $value['en'] ?? reset($value);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}

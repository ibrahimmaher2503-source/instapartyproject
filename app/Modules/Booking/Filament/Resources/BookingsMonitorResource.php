<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources;

use App\Modules\Booking\Application\Actions\ForceCancelBookingAction;
use App\Modules\Booking\Application\Actions\GetNegotiationMonitorQueryAction;
use App\Modules\Booking\Application\DTOs\AdminInterventionDTO;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\PaymentStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\BookingLifecycleState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CompletedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Filament\Resources\BookingsMonitorResource\Pages\ListBookingsMonitor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Shared\Filament\Tables\ReferenceSearch;
use Brick\Money\Money;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use DomainException;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column as ExcelColumn;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class BookingsMonitorResource extends Resource
{
    protected static ?string $model = Booking::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.operations');
    }

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'bookings-monitor';

    public static function getNavigationLabel(): string
    {
        return __('booking.nav.negotiation_monitor');
    }

    public static function getModelLabel(): string
    {
        return __('booking.models.negotiation_monitor.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking.models.negotiation_monitor.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return app(GetNegotiationMonitorQueryAction::class)
            ->execute()
            ->with(['customer', 'vendors.vendor']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_no')
                    ->searchable(query: fn (Builder $query, string $search): Builder => ReferenceSearch::apply($query, $search, [
                        '' => ['public_id', 'reference_no'],
                        'customer' => ['public_id', 'name', 'email', 'phone_e164'],
                        'vendors.vendor' => ['public_id', 'business_name->en', 'business_name->ar'],
                        'vendors.vendor.user' => ['public_id', 'name', 'email', 'phone_e164'],
                    ]))
                    ->sortable()
                    ->label(__('booking.columns.reference_no')),

                TextColumn::make('customer.name')
                    ->label(__('booking.columns.customer_id'))
                    ->searchable()
                    ->default(__('shared::admin-feedback.unknown_customer')),

                TextColumn::make('vendor_names')
                    ->label(__('booking.columns.vendor'))
                    ->getStateUsing(fn (Booking $record): string => $record->vendors
                        ->map(fn (BookingVendor $vendorBooking): ?string => $vendorBooking->vendor?->business_name)
                        ->filter()
                        ->unique()
                        ->join(', ') ?: __('shared::admin-feedback.unavailable_reference'))
                    ->limit(40)
                    ->wrap(),

                TextColumn::make('lifecycle_status')
                    ->badge()
                    ->color(fn (mixed $state): string => match (true) {
                        $state instanceof VendorReviewState => 'warning',
                        $state instanceof CustomerReviewState => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof BookingLifecycleState
                        ? __('booking.lifecycle_status.'.$state->getValue())
                        : (string) $state)
                    ->label(__('booking.columns.lifecycle_status')),

                TextColumn::make('total_minor')
                    ->money('EGP', divideBy: 100)
                    ->sortable()
                    ->label(__('booking.columns.total')),

                TextColumn::make('event_starts_at')
                    ->dateTime()
                    ->label(__('booking.columns.event_starts_at')),

                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('booking.columns.submitted_at')),

                TextColumn::make('queue_age')
                    ->label(__('booking.columns.queue_age'))
                    ->getStateUsing(fn (Booking $record): string => Carbon::parse($record->submitted_at ?? $record->created_at, 'UTC')
                        ->diffForHumans(now('UTC'))),

                TextColumn::make('vendors_min_deadline')
                    ->label(__('booking.columns.nearest_deadline'))
                    ->dateTime()
                    ->sortable()
                    ->getStateUsing(fn (Booking $record): ?string => $record->vendors()
                        ->where('sub_status', VendorSubStatus::Pending->value)
                        ->whereNotNull('response_deadline')
                        ->min('response_deadline')
                    ),
                TextColumn::make('negotiation_urgency')
                    ->label(__('booking.columns.urgency'))
                    ->badge()
                    ->getStateUsing(fn (Booking $record): string => self::urgencyLabel($record))
                    ->color(fn (Booking $record): string => self::isOverdue($record) ? 'danger' : 'success'),
            ])
            ->filters([
                SelectFilter::make('lifecycle_status')
                    ->options([
                        LifecycleStatus::VendorReview->value => __('booking.lifecycle_status.vendor_review'),
                        LifecycleStatus::CustomerReview->value => __('booking.lifecycle_status.customer_review'),
                    ])
                    ->label(__('booking.columns.lifecycle_status')),
                SelectFilter::make('payment_status')
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $s) => [$s->value => $s->label()])->all())
                    ->label(__('booking.columns.payment_status')),
                SelectFilter::make('product_type')
                    ->label(__('catalog.product_type'))
                    ->options(collect(ProductType::cases())->mapWithKeys(fn (ProductType $t) => [$t->value => $t->value])->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->whereHas('items', fn ($q) => $q->where('product_type', $data['value']))
                        : $query),
                SelectFilter::make('negotiation_scope')
                    ->label(__('booking.filters.negotiation_scope'))
                    ->options([
                        'late' => __('booking.filters.negotiation_late'),
                        'open' => __('booking.filters.negotiation_open'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $scope = $data['value'] ?? null;

                        return app(GetNegotiationMonitorQueryAction::class)
                            ->execute(is_string($scope) ? $scope : null, query: $query);
                    }),
                Filter::make('event_date')
                    ->form([
                        DatePicker::make('from')->label(__('booking.filters.event_date_from')),
                        DatePicker::make('until')->label(__('booking.filters.event_date_until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['from'] ?? null), fn (Builder $query): Builder => $query->whereDate('event_starts_at', '>=', $data['from']))
                        ->when(filled($data['until'] ?? null), fn (Builder $query): Builder => $query->whereDate('event_starts_at', '<=', $data['until']))),
                Filter::make('total_range')
                    ->form([
                        TextInput::make('min')
                            ->numeric()
                            ->minValue(0)
                            ->maxLength(21)
                            ->rules(['nullable', 'regex:/^\d{1,18}(?:\.\d{1,2})?$/D'])
                            ->label(__('booking.filters.total_min')),
                        TextInput::make('max')
                            ->numeric()
                            ->minValue(0)
                            ->maxLength(21)
                            ->rules(['nullable', 'regex:/^\d{1,18}(?:\.\d{1,2})?$/D'])
                            ->label(__('booking.filters.total_max')),
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
                            ->when($minimum !== null || $maximum !== null, fn (Builder $query): Builder => $query->where('total_currency', 'EGP'))
                            ->when($minimum !== null, fn (Builder $query): Builder => $query->where('total_minor', '>=', $minimum))
                            ->when($maximum !== null, fn (Builder $query): Builder => $query->where('total_minor', '<=', $maximum));
                    }),
                SelectFilter::make('vendor_id')
                    ->label(__('booking.filters.vendor_filter'))
                    ->searchable()
                    ->options(fn (): array => BookingVendor::query()
                        ->whereHas('booking', fn (Builder $query): Builder => $query->whereIn('lifecycle_status', [
                            LifecycleStatus::VendorReview->value,
                            LifecycleStatus::CustomerReview->value,
                        ]))
                        ->selectRaw('MIN(id) as id, vendor_profile_id')
                        ->groupBy('vendor_profile_id')
                        ->with('vendor')
                        ->get()
                        ->mapWithKeys(fn (BookingVendor $vendorBooking): array => [
                            $vendorBooking->vendor_profile_id => $vendorBooking->vendor?->business_name ?: __('booking.filters.unknown_vendor'),
                        ])
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('vendors', fn (Builder $query): Builder => $query->where('vendor_profile_id', $data['value']))
                        : $query),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label(__('booking.actions.export_filtered_monitor'))
                    ->authorize('viewAny', Booking::class)
                    ->exports([
                        ExcelExport::make('filtered_bookings')
                            ->fromTable()
                            ->only([
                                'public_id',
                                'reference_no',
                                'lifecycle_status',
                                'payment_status',
                                'total_minor',
                                'total_currency',
                                'submitted_at',
                                'event_starts_at',
                                'event_ends_at',
                            ])
                            ->withColumns([
                                ExcelColumn::make('public_id')->heading(__('booking.columns.public_id'))
                                    ->getStateUsing(fn (Booking $record): string => (string) $record->public_id),
                                ExcelColumn::make('reference_no')->heading(__('booking.columns.reference_no'))
                                    ->getStateUsing(fn (Booking $record): string => (string) $record->reference_no),
                                ExcelColumn::make('lifecycle_status')->heading(__('booking.columns.lifecycle_status'))
                                    ->getStateUsing(fn (Booking $record): string => __('booking.lifecycle_status.'.$record->lifecycle_status->getValue())),
                                ExcelColumn::make('payment_status')->heading(__('booking.columns.payment_status'))
                                    ->getStateUsing(fn (Booking $record): string => __('booking.payment_status.'.$record->payment_status->getValue())),
                                ExcelColumn::make('total_minor')->heading(__('booking.columns.total_minor'))
                                    ->getStateUsing(fn (Booking $record): string => (string) $record->total_minor),
                                ExcelColumn::make('total_currency')->heading(__('booking.columns.total_currency'))
                                    ->getStateUsing(fn (Booking $record): string => (string) $record->total_currency),
                                ExcelColumn::make('submitted_at')->heading(__('booking.columns.submitted_at'))
                                    ->getStateUsing(fn (Booking $record): ?string => $record->submitted_at?->copy()->utc()->toIso8601String()),
                                ExcelColumn::make('event_starts_at')->heading(__('booking.columns.event_starts_at'))
                                    ->getStateUsing(fn (Booking $record): ?string => $record->event_starts_at?->copy()->utc()->toIso8601String()),
                                ExcelColumn::make('event_ends_at')->heading(__('booking.columns.event_ends_at'))
                                    ->getStateUsing(fn (Booking $record): ?string => $record->event_ends_at?->copy()->utc()->toIso8601String()),
                            ]),
                    ]),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('forceCancel')
                    ->label(__('booking.actions.force_cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('booking.modals.force_cancel_title'))
                    ->modalDescription(fn (Booking $record): string => BookingResource::forceCancelImpactSummary($record))
                    ->form([
                        Textarea::make('reason')
                            ->label(__('booking.modals.force_cancel_reason'))
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000),
                    ])
                    ->visible(fn (Booking $record): bool => auth()->user()?->can('force_cancel_booking') === true
                        && ! ($record->lifecycle_status instanceof CancelledState)
                        && ! ($record->lifecycle_status instanceof CompletedState)
                    )
                    ->action(function (Booking $record, array $data): void {
                        abort_unless(auth()->user()?->can('force_cancel_booking') === true, 403);

                        try {
                            app(ForceCancelBookingAction::class)->execute(
                                $record,
                                new AdminInterventionDTO(
                                    bookingId: $record->id,
                                    adminId: (int) auth()->id(),
                                    interventionType: InterventionType::ForceCancel,
                                    reason: $data['reason'],
                                ),
                            );
                        } catch (DomainException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->body(__('shared::admin-feedback.error_reference', ['reference' => $record->public_id]))
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('booking.notifications.force_cancelled'))
                            ->success()
                            ->send();
                    }),
            ])
            ->contentGrid(['default' => 1, 'lg' => 2])
            ->defaultSort('submitted_at', 'asc')
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookingsMonitor::route('/'),
        ];
    }

    private static function isOverdue(Booking $record): bool
    {
        return $record->vendors()
            ->where('sub_status', VendorSubStatus::Pending->value)
            ->whereNotNull('response_deadline')
            ->where('response_deadline', '<', now('UTC'))
            ->exists();
    }

    private static function urgencyLabel(Booking $record): string
    {
        $pendingVendors = $record->vendors()->where('sub_status', VendorSubStatus::Pending->value);
        if (! $pendingVendors->exists()) {
            return __('shared::admin-feedback.urgency_not_applicable');
        }

        $deadline = $record->vendors()
            ->where('sub_status', VendorSubStatus::Pending->value)
            ->whereNotNull('response_deadline')
            ->min('response_deadline');

        if ($deadline === null) {
            return __('shared::admin-feedback.urgency_unavailable');
        }

        if (Carbon::parse($deadline, 'UTC')->isFuture()) {
            return __('shared::admin-feedback.urgency_on_time');
        }

        $minutes = max(1, Carbon::parse($deadline, 'UTC')->diffInMinutes(now('UTC')));

        return __('booking.urgency.overdue').' · '.CarbonInterval::minutes($minutes)->cascade()->forHumans();
    }
}

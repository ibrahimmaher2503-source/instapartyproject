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
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\BookingLifecycleState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Filament\Resources\BookingsMonitorResource\Pages\ListBookingsMonitor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use Carbon\CarbonInterval;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsMonitorResource extends Resource
{
    protected static ?string $model = Booking::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.operations');
    }

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 50;

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
            ->with(['customer']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_no')
                    ->searchable()
                    ->sortable()
                    ->label(__('booking.columns.reference_no')),

                TextColumn::make('customer.name')
                    ->label(__('booking.columns.customer_id'))
                    ->searchable()
                    ->default(fn (Booking $record): string => '#'.$record->customer_id),

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

                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('booking.columns.submitted_at')),

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
            ])
            ->actions([
                ViewAction::make(),

                Action::make('forceCancel')
                    ->label(__('booking.actions.force_cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('booking.modals.force_cancel_title'))
                    ->modalDescription(__('booking.modals.force_cancel_description'))
                    ->form([
                        Textarea::make('reason')
                            ->label(__('booking.modals.force_cancel_reason'))
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000),
                    ])
                    ->visible(fn (Booking $record): bool => auth()->user()?->can('force_cancel_booking') === true
                        && $record->lifecycle_status !== LifecycleStatus::Completed
                    )
                    ->action(function (Booking $record, array $data): void {
                        app(ForceCancelBookingAction::class)->execute(
                            $record,
                            new AdminInterventionDTO(
                                bookingId: $record->id,
                                adminId: (int) auth()->id(),
                                interventionType: InterventionType::ForceCancel,
                                reason: $data['reason'],
                            ),
                        );

                        Notification::make()
                            ->title(__('booking.notifications.force_cancelled'))
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('submitted_at', 'desc')
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
        $deadline = $record->vendors()
            ->where('sub_status', VendorSubStatus::Pending->value)
            ->whereNotNull('response_deadline')
            ->min('response_deadline');

        if ($deadline === null || Carbon\Carbon::parse($deadline, 'UTC')->isFuture()) {
            return __('booking.urgency.on_time');
        }

        $minutes = max(1, Carbon\Carbon::parse($deadline, 'UTC')->diffInMinutes(now('UTC')));

        return __('booking.urgency.overdue').' · '.CarbonInterval::minutes($minutes)->cascade()->forHumans();
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\BookingResource\Pages;

use App\Modules\Booking\Application\Actions\AddTransportationAdjustmentAction;
use App\Modules\Booking\Application\Actions\ForceCancelBookingAction;
use App\Modules\Booking\Application\DTOs\AdminInterventionDTO;
use App\Modules\Booking\Application\DTOs\CreateBookingDraftDTO;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CompletedState;
use App\Modules\Booking\Filament\Resources\BookingResource;
use Brick\Money\Money;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    public function callMountedAction(array $arguments = []): mixed
    {
        $terminalReason = null;
        if (end($this->mountedActions) === 'forceCancel') {
            abort_unless(auth()->user()?->hasAnyRole(['admin', 'booking_manager', 'super_admin']) === true, 403);
            $state = $this->getRecord()->fresh()?->lifecycle_status;
            $terminalReason = match (true) {
                $state instanceof CancelledState => __('booking::booking.cancellation.blocked.already_cancelled'),
                $state instanceof CompletedState => __('booking::booking.cancellation.blocked.booking_completed'),
                default => null,
            };
        }

        if ($terminalReason !== null) {
            Notification::make()
                ->title($terminalReason)
                ->body(__('shared::admin-feedback.force_cancel_failed', ['reference' => $this->getRecord()->public_id]))
                ->danger()
                ->send();
            $this->unmountAction();

            return null;
        }

        return parent::callMountedAction($arguments);
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing([
            'customer',
            'customerNotes',
            'occasion',
            'address.city',
            'vendors.vendor',
            'items.service',
            'payments',
            'snapshots.triggeredBy',
            'stateTransitions.triggeredByUser',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label(__('booking.actions.edit'))
                ->icon('heroicon-o-pencil-square')
                ->button()
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'booking_manager', 'super_admin']) === true)
                ->modalHeading(__('booking.actions.edit'))
                ->modalSubmitActionLabel(__('booking.actions.save_changes'))
                ->form([
                    TextInput::make('guest_count')
                        ->label(__('booking.columns.guest_count'))
                        ->numeric()
                        ->rules(['integer'])
                        ->minValue(1)
                        ->maxValue(CreateBookingDraftDTO::MAX_GUEST_COUNT)
                        ->step(1)
                        ->validationMessages([
                            'integer' => __('booking.validation.guest_count_range'),
                            'min' => __('booking.validation.guest_count_range'),
                            'max' => __('booking.validation.guest_count_range'),
                        ])
                        ->helperText(__('booking.validation.guest_count_range'))
                        ->default(fn (): ?int => $this->record->guest_count)
                        ->required(),
                    DateTimePicker::make('event_starts_at')
                        ->label(__('booking.columns.event_starts_at'))
                        ->seconds(false)
                        ->default(fn (): mixed => $this->record->event_starts_at)
                        ->required(),
                    DateTimePicker::make('event_ends_at')
                        ->label(__('booking.columns.event_ends_at'))
                        ->seconds(false)
                        ->default(fn (): mixed => $this->record->event_ends_at)
                        ->required(),
                ])
                ->action(function (Booking $record, array $data): void {
                    $record->update([
                        'guest_count' => $data['guest_count'],
                        'event_starts_at' => $data['event_starts_at'],
                        'event_ends_at' => $data['event_ends_at'],
                    ]);

                    Notification::make()
                        ->title(__('booking.notifications.booking_updated'))
                        ->success()
                        ->send();
                }),

            Action::make('forceCancel')
                ->label(__('booking.actions.force_cancel'))
                ->icon('heroicon-o-x-circle')
                ->button()
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
                ->visible(fn (Booking $record): bool => auth()->user()?->hasAnyRole(['admin', 'booking_manager', 'super_admin']) === true
                    && ! ($record->lifecycle_status instanceof CancelledState)
                    && ! ($record->lifecycle_status instanceof CompletedState))
                ->action(function (Booking $record, array $data): void {
                    abort_unless(auth()->user()?->hasAnyRole(['admin', 'booking_manager', 'super_admin']) === true, 403);

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
                            ->body(__('shared::admin-feedback.force_cancel_failed', ['reference' => $record->public_id]))
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('booking.notifications.force_cancelled'))
                        ->success()
                        ->send();
                }),

            Action::make('addAdminNote')
                ->label(__('booking.actions.add_admin_note'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->button()
                ->color('gray')
                ->form([
                    Textarea::make('note')
                        ->label(__('booking.modals.admin_note_body'))
                        ->required()
                        ->minLength(3)
                        ->maxLength(1000)
                        ->rows(5),
                ])
                ->visible(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'booking_manager', 'super_admin']) === true)
                ->action(function (Booking $record, array $data): void {
                    $state = [
                        'lifecycle_status' => $record->lifecycle_status->getValue(),
                        'payment_status' => $record->payment_status->getValue(),
                        'fulfillment_status' => $record->fulfillment_status->value,
                    ];

                    BookingAdminIntervention::create([
                        'public_id' => (string) Str::ulid(),
                        'booking_id' => $record->id,
                        'admin_id' => (int) auth()->id(),
                        'intervention_type' => InterventionType::AdminNote,
                        'reason' => $data['note'],
                        'before_state' => $state,
                        'after_state' => $state,
                    ]);

                    Notification::make()
                        ->title(__('booking.notifications.admin_note_added'))
                        ->success()
                        ->send();
                }),

            Action::make('addTransportationLine')
                ->label(__('booking.actions.add_transportation_line'))
                ->icon('heroicon-o-truck')
                ->button()
                ->color('warning')
                ->form([
                    TextInput::make('amount')
                        ->label(__('booking.columns.transportation_adjustment').' (EGP)')
                        ->numeric()
                        ->step('0.01')
                        ->rules(['decimal:0,2'])
                        ->validationMessages([
                            'decimal' => __('booking.validation.transport_price_invalid'),
                            'numeric' => __('booking.validation.transport_price_invalid'),
                            'min' => __('booking.validation.transport_price_invalid'),
                        ])
                        ->minValue(0)
                        ->required(),
                    Textarea::make('source')
                        ->label(__('booking.columns.adjustment_source'))
                        ->required()
                        ->minLength(3)
                        ->maxLength(120),
                    TextInput::make('source_reference')
                        ->label(__('booking.columns.adjustment_reference'))
                        ->maxLength(120),
                ])
                ->visible(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'booking_manager', 'super_admin']) === true)
                ->action(function (Booking $record, array $data): void {
                    $minor = Money::of((string) $data['amount'], 'EGP')->getMinorAmount()->toInt();

                    app(AddTransportationAdjustmentAction::class)->execute(
                        $record,
                        (int) auth()->id(),
                        $minor,
                        $data['source'],
                        $data['source_reference'] ?? null,
                    );

                    Notification::make()
                        ->title(__('booking.notifications.transportation_line_added'))
                        ->success()
                        ->send();
                }),

            Action::make('printInvoice')
                ->label(__('booking.invoice.print'))
                ->icon('heroicon-o-printer')
                ->url(fn (Booking $record): string => route('admin.bookings.invoice', $record->public_id))
                ->openUrlInNewTab(),
        ];
    }
}

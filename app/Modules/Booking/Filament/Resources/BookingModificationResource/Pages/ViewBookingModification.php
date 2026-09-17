<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\BookingModificationResource\Pages;

use App\Modules\Booking\Application\Actions\AdminForwardModificationAction;
use App\Modules\Booking\Application\Actions\AdminWithdrawModificationAction;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Filament\Resources\BookingModificationResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewBookingModification extends ViewRecord
{
    protected static string $resource = BookingModificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('forwardToCustomer')
                ->label(__('booking.modification_actions.forward_to_customer'))
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading(__('booking.modification_actions.forward_confirm_title'))
                ->modalDescription(__('booking.modification_actions.forward_confirm_body'))
                ->visible(fn (): bool => $this->getRecord()->status === ModificationStatus::Draft)
                ->action(function (): void {
                    /** @var BookingModification $record */
                    $record = $this->getRecord();
                    try {
                        app(AdminForwardModificationAction::class)->execute($record, auth()->id());
                        Notification::make()
                            ->title(__('booking.modification_actions.forwarded_success'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title(__('booking.modification_actions.action_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('withdraw')
                ->label(__('booking.modification_actions.withdraw'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('booking.modification_actions.withdraw_confirm_title'))
                ->modalDescription(__('booking.modification_actions.withdraw_confirm_body'))
                ->visible(fn (): bool => in_array(
                    $this->getRecord()->status,
                    [ModificationStatus::Draft, ModificationStatus::Pending],
                    strict: true,
                ))
                ->action(function (): void {
                    /** @var BookingModification $record */
                    $record = $this->getRecord();
                    try {
                        app(AdminWithdrawModificationAction::class)->execute($record, auth()->id());
                        Notification::make()
                            ->title(__('booking.modification_actions.withdrawn_success'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title(__('booking.modification_actions.action_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}

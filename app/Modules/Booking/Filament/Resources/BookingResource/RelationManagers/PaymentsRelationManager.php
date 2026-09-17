<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers;

use App\Modules\Payments\Application\Actions\InitiateRefundAction;
use App\Modules\Payments\Application\Actions\ManualCapturePaymentAction;
use App\Modules\Payments\Application\Actions\VoidStuckAuthorizationAction;
use App\Modules\Payments\Application\DTOs\InitiateRefundDto;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Filament\Resources\PaymentResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class PaymentsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('booking.relations.payments');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Payment $record): string => PaymentResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('payments.columns.public_id'))
                    ->copyable()
                    ->searchable(),
                TextColumn::make('gateway')
                    ->label(__('payments.columns.gateway'))
                    ->searchable(),
                TextColumn::make('amount_minor')
                    ->label(__('payments.columns.amount'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),
                TextColumn::make('method')
                    ->label(__('payments.columns.method'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('payments.columns.status'))
                    ->badge(),
                TextColumn::make('captured_at')
                    ->label(__('payments.columns.captured_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([
                Action::make('capture')
                    ->label('Capture')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Payment $record): bool => $record->status === PaymentStatus::Authorized)
                    ->requiresConfirmation()
                    ->form([
                        TextInput::make('reason')
                            ->label('Reason')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        try {
                            app(ManualCapturePaymentAction::class)->execute($record->id, (int) auth()->id(), $data['reason']);
                            Notification::make()->title('Payment captured successfully.')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Capture failed: '.$e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('void')
                    ->label('Void')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Payment $record): bool => $record->status === PaymentStatus::Authorized)
                    ->requiresConfirmation()
                    ->form([
                        TextInput::make('reason')
                            ->label('Reason')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        try {
                            app(VoidStuckAuthorizationAction::class)->execute($record->id, (int) auth()->id(), $data['reason']);
                            Notification::make()->title('Payment voided.')->warning()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Void failed: '.$e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (Payment $record): bool => $record->status === PaymentStatus::Captured)
                    ->requiresConfirmation()
                    ->form([
                        Select::make('reason_code')
                            ->label('Reason')
                            ->options(collect(RefundReasonCode::cases())->mapWithKeys(fn ($c) => [$c->value => ucwords(str_replace('_', ' ', $c->value))]))
                            ->required(),
                        Textarea::make('reason_note')
                            ->label('Note')
                            ->rows(2),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        try {
                            $dto = new InitiateRefundDto(
                                paymentId: $record->id,
                                bookingId: $record->booking_id,
                                initiatedBy: (int) auth()->id(),
                                reasonCode: RefundReasonCode::from($data['reason_code']),
                                reasonNotes: ['en' => $data['reason_note'] ?? ''],
                            );
                            app(InitiateRefundAction::class)->execute($dto);
                            Notification::make()->title('Refund initiated.')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Refund failed: '.$e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading(__('booking.relations.payments'))
            ->emptyStateDescription(__('booking.empty_states.payments'))
            ->defaultSort('created_at', 'desc');
    }
}

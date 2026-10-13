<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers;

use App\Modules\Payments\Application\Actions\ManualCapturePaymentAction;
use App\Modules\Payments\Application\Actions\VoidStuckAuthorizationAction;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Exceptions\PartialRefundUnsupportedException;
use App\Modules\Payments\Domain\Exceptions\RefundPolicyViolationException;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Filament\Actions\RefundPaymentAction;
use App\Modules\Payments\Filament\Resources\PaymentResource;
use App\Modules\Settlement\Domain\Exceptions\OverRefundAttemptedException;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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
                            $this->notifyFailure($e);
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
                            $this->notifyFailure($e);
                        }
                    }),

                RefundPaymentAction::make(),
            ])
            ->bulkActions([])
            ->emptyStateHeading(__('booking.relations.payments'))
            ->emptyStateDescription(__('booking.empty_states.payments'))
            ->defaultSort('created_at', 'desc');
    }

    private function notifyFailure(Throwable $exception): void
    {
        if ($exception instanceof ValidationException) {
            $messages = collect($exception->errors())->flatten();

            if ($messages->contains(fn ($message): bool => is_string($message) && str_starts_with($message, 'Gateway void failed:'))) {
                $this->notifyUnexpectedFailure($exception, 'payments::payments.errors.gateway_void_failed', sanitizeReport: true);

                return;
            }

            $messageKey = match (true) {
                $messages->contains('Only authorized payments can be manually captured.') => 'payments::payments.errors.capture_not_authorized',
                $messages->contains('Only authorized payments can be voided.') => 'payments::payments.errors.void_not_authorized',
                default => null,
            };

            $this->notifyKnownFailure($messageKey === null ? __('shared::shared.errors.validation_failed') : __($messageKey));

            return;
        }

        if ($exception instanceof RefundPolicyViolationException) {
            $this->notifyKnownFailure(__($exception->reasonMessageKey));

            return;
        }

        if ($exception instanceof PartialRefundUnsupportedException) {
            $this->notifyKnownFailure(__('payments::refunds.errors.partial_refund_unsupported'));

            return;
        }

        if ($exception instanceof OverRefundAttemptedException) {
            $this->notifyKnownFailure(__('payments::refunds.errors.over_refund_attempted'));

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $messageKey = match ($exception->getStatusCode()) {
                404 => 'payments::payments.errors.payment_not_found',
                409 => 'payments::payments.errors.refund_conflict',
                default => null,
            };

            if ($messageKey !== null) {
                $this->notifyKnownFailure(__($messageKey));

                return;
            }
        }

        $this->notifyUnexpectedFailure($exception);
    }

    private function notifyKnownFailure(string $message): void
    {
        Notification::make()
            ->title(__('shared::shared.errors.action_failed'))
            ->body($message)
            ->danger()
            ->send();
    }

    private function notifyUnexpectedFailure(
        Throwable $exception,
        ?string $safeMessageKey = null,
        bool $sanitizeReport = false,
    ): void {
        $traceId = Context::get('trace_id');

        if (! is_string($traceId) || trim($traceId) === '') {
            $traceId = (string) Str::uuid();
            Context::add('trace_id', $traceId);
        }

        report($sanitizeReport ? new RuntimeException('Payment gateway void failed.') : $exception);

        $message = $safeMessageKey === null
            ? __('shared::shared.errors.unexpected_failure')
            : __($safeMessageKey);
        $reference = __('shared::shared.errors.reference_id', ['id' => $traceId]);

        Notification::make()
            ->title(__('shared::shared.errors.action_failed'))
            ->body($message.' '.$reference)
            ->danger()
            ->send();
    }
}

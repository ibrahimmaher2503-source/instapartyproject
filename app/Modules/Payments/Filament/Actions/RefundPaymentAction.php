<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Actions;

use App\Modules\Payments\Application\Actions\InitiateRefundAction;
use App\Modules\Payments\Application\DTOs\InitiateRefundDto;
use App\Modules\Payments\Domain\Contracts\PaymentsBookingReader;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Exceptions\RefundPolicyViolationException;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Settlement\Domain\Exceptions\OverRefundAttemptedException;
use Brick\Money\Money;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class RefundPaymentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'refund';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->label(__('payments::refunds.actions.initiate'))
            ->icon('heroicon-o-arrow-uturn-left')->color('warning')
            ->visible(fn (Payment $record): bool => Gate::allows('payment.refund')
                && in_array($record->status->getValue(), ['captured', 'partially_refunded'], true))
            ->requiresConfirmation()
            ->modalDescription(__('payments::refunds.actions.confirm'))
            ->form([
                TextInput::make('amount')->label(fn (Payment $record): string => __('payments::refunds.actions.amount').' ('.$record->amount_currency.')')
                    ->helperText(__('payments::refunds.actions.amount_help'))
                    ->numeric()->step('0.01')->required()->minValue('0.01')
                    ->rules(['regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/'])
                    ->default(fn (Payment $record): string => (string) Money::ofMinor(self::remainingMinor($record), $record->amount_currency)->getAmount())
                    ->maxValue(fn (Payment $record): string => (string) Money::ofMinor(self::remainingMinor($record), $record->amount_currency)->getAmount()),
                Select::make('reason_code')->label(__('payments::refunds.columns.reason_code'))
                    ->options(collect(RefundReasonCode::cases())->mapWithKeys(fn (RefundReasonCode $reason): array => [
                        $reason->value => __('payments::refunds.reason_code.'.$reason->value),
                    ]))->required(),
                Textarea::make('reason_notes.en')->label(__('payments::refunds.actions.note_en'))->required()->maxLength(2000),
                Textarea::make('reason_notes.ar')->label(__('payments::refunds.actions.note_ar'))->required()->maxLength(2000),
                Hidden::make('idempotency_key')->default(fn (): string => (string) Str::uuid())->required(),
            ])
            ->action(function (Payment $record, array $data): void {
                Gate::authorize('payment.refund');
                try {
                    $refund = app(InitiateRefundAction::class)->execute(new InitiateRefundDto(
                        paymentId: $record->id,
                        bookingId: $record->booking_id,
                        initiatedBy: (int) auth()->id(),
                        reasonCode: RefundReasonCode::from($data['reason_code']),
                        reasonNotes: $data['reason_notes'],
                        requestedAmountMinor: Money::of((string) $data['amount'], $record->amount_currency)->getMinorAmount()->toInt(),
                        idempotencyKey: hash('sha256', 'panel-refund|'.auth()->id().'|'.$record->public_id.'|'.$data['idempotency_key']),
                    ));
                    Notification::make()->title(__('payments::refunds.actions.recorded'))
                        ->body(__('payments::refunds.status.'.$refund->status->value))->success()->send();
                } catch (OverRefundAttemptedException) {
                    throw ValidationException::withMessages(['amount' => __('payments::refunds.errors.over_refund_attempted')]);
                } catch (RefundPolicyViolationException $exception) {
                    throw ValidationException::withMessages(['reason_code' => __($exception->reasonMessageKey)]);
                } catch (ValidationException $exception) {
                    throw $exception;
                } catch (HttpExceptionInterface $exception) {
                    Notification::make()->title(__('shared::shared.errors.action_failed'))
                        ->body($exception->getMessage() === __('payments::refunds.errors.refund_in_progress')
                            ? __('payments::refunds.errors.refund_in_progress')
                            : __('payments::payments.errors.refund_conflict'))->danger()->send();
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title(__('shared::shared.errors.action_failed'))
                        ->body(__('shared::shared.errors.unexpected_failure'))->danger()->send();
                }
            });
    }

    private static function remainingMinor(Payment $payment): int
    {
        $bookingBudget = app(PaymentsBookingReader::class)->refundCashBudget((int) $payment->booking_id);
        if ($bookingBudget === null || $bookingBudget->getCurrency()->getCurrencyCode() !== $payment->amount_currency) {
            return 0;
        }
        $bookingReservedMinor = (int) Refund::query()->where('booking_id', $payment->booking_id)
            ->where('status', '!=', 'failed')->sum('amount_minor');
        $reservedMinor = (int) Refund::query()->where('payment_id', $payment->id)
            ->where('status', '!=', 'failed')->sum('amount_minor');

        return Money::max(Money::ofMinor(0, $payment->amount_currency), Money::min(
            $bookingBudget->minus(Money::ofMinor($bookingReservedMinor, $payment->amount_currency)),
            Money::ofMinor($payment->amount_minor, $payment->amount_currency)
                ->minus(Money::ofMinor($reservedMinor, $payment->amount_currency))))
            ->getMinorAmount()->toInt();
    }
}

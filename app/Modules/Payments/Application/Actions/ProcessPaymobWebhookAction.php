<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Events\PaymentFailed;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use App\Modules\Payments\Infrastructure\Support\MapPaymobFailureCode;
use App\Modules\Payments\Infrastructure\Support\RedactPciFields;
use App\Modules\Settlement\Domain\Exceptions\DuplicateIdempotencyKeyWithDifferentPayloadException;
use App\Modules\Shared\Application\Services\IdempotencyService;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessPaymobWebhookAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly EloquentPaymentRepository $payments,
        private readonly CapturePaymentAction $capture,
        private readonly ProcessRefundAction $processRefund,
        private readonly IdempotencyService $idempotency,
    ) {}

    /** @param array<string, mixed> $payload */
    public function execute(array $payload, string $signature): void
    {
        $valid = $this->gateway->verifyWebhookSignature($payload, $signature);
        $log = GatewayWebhookLog::query()->create([
            'gateway' => 'paymob',
            'event_type' => (string) ($payload['type'] ?? 'unknown'),
            'signature_valid' => $valid,
            'payload' => RedactPciFields::redact($payload),
            'processing_error' => $valid ? null : 'invalid_signature',
            'created_at' => now(),
        ]);

        abort_unless($valid, 401, 'Invalid signature');

        try {
            $dto = $this->gateway->parseWebhook($payload);
        } catch (Throwable $exception) {
            $log->update(['processing_error' => 'malformed_payload']);

            throw $exception;
        }

        if (trim($dto->gatewayRef) === '') {
            $log->update(['processing_error' => 'malformed_payload']);

            return;
        }

        // Derive a deterministic idempotency key from the stable webhook fields.
        // This makes duplicate/replayed webhooks unconditionally idempotent.
        $obj = $payload['obj'] ?? [];
        $orderId = (string) ($obj['order']['id'] ?? $obj['order_id'] ?? '');
        $transactionId = (string) ($obj['id'] ?? '');
        $success = $dto->success ? 'true' : 'false';
        // The prior REFUND key could be stored before local confirmation. Give
        // provider-state confirmation its own namespace so that cache cannot hide it.
        $idempotencyKey = $dto->transactionType === 'REFUND'
            ? "paymob:REFUND:confirmation-v2:{$orderId}:{$transactionId}:{$success}"
            : "paymob:{$dto->transactionType}:{$orderId}:{$transactionId}:{$success}";

        // Short-circuit duplicate webhooks; the callback returns the stored result.
        try {
            $alreadyProcessed = $this->idempotency->hasBeenUsed('internal_webhook', $idempotencyKey);
        } catch (DuplicateIdempotencyKeyWithDifferentPayloadException $exception) {
            $log->update(['processing_error' => 'duplicate_idempotent_replay']);

            throw $exception;
        }

        if ($alreadyProcessed) {
            $log->update(['processing_error' => 'duplicate_idempotent_replay']);

            return;
        }

        $payment = $this->payments->findByGatewayRef('paymob', $dto->gatewayRef);
        if ($payment === null && $dto->orderId !== null) {
            $payment = Payment::query()
                ->where('gateway', 'paymob')
                ->where('metadata->paymob_order_id', $dto->orderId)
                ->first();
        }
        if ($payment === null) {
            $log->update(['processing_error' => 'unknown_gateway_reference']);

            return;
        }

        if ($dto->transactionType === 'REFUND') {
            if (! $dto->success) {
                $log->update(['processing_error' => 'refund_confirmation_deferred']);

                return;
            }

            try {
                if (! $this->processRefund->confirmFromWebhook($payment->id, $dto->gatewayRef)) {
                    $log->update(['processing_error' => 'refund_confirmation_deferred']);

                    return;
                }

                $this->idempotency->remember('internal_webhook', $idempotencyKey, $idempotencyKey, fn () => true);
                $log->update(['processed_at' => now()]);
            } catch (DuplicateIdempotencyKeyWithDifferentPayloadException $exception) {
                $log->update(['processing_error' => 'duplicate_refund_idempotent_replay']);

                throw $exception;
            } catch (Throwable $exception) {
                $log->update(['processing_error' => 'processing_failed']);

                throw $exception;
            }

            return;
        }

        if ($dto->transactionType !== 'REFUND' && $dto->success) {
            if ($dto->capturedAmount === null
                || $dto->capturedAmount->getMinorAmount()->toInt() !== (int) $payment->amount_minor
                || $dto->capturedAmount->getCurrency()->getCurrencyCode() !== (string) $payment->amount_currency) {
                $log->update(['processing_error' => 'captured_amount_or_currency_mismatch']);

                return;
            }
        }

        $callbackExecuted = false;
        $rememberWebhook = function () use ($dto, $idempotencyKey, $payment, $log, &$callbackExecuted): void {
            $this->idempotency->remember('internal_webhook', $idempotencyKey, $idempotencyKey, function () use (
                $dto,
                $payment,
                $log,
                &$callbackExecuted,
            ): bool {
                $callbackExecuted = true;
                // A rolled-back attempt leaves Eloquent originals ahead of persisted state.
                $payment->refresh();
                $log->refresh();

                if ($dto->success) {
                    $payment->update(['metadata' => array_merge($payment->metadata ?? [], [
                        'paymob_transaction_id' => $dto->gatewayRef,
                        'paymob_order_id' => $dto->orderId ?? ($payment->metadata['paymob_order_id'] ?? null),
                    ])]);
                    try {
                        $this->capture->execute(
                            paymentId: $payment->id,
                            idempotencyKey: "capture:{$payment->id}",
                            capturedAmount: $dto->capturedAmount,
                        );
                    } catch (Throwable $exception) {
                        $log->update(['processing_error' => 'processing_failed']);

                        throw $exception;
                    }
                    $log->update(['processed_at' => now()]);

                    return true;
                }

                DB::transaction(function () use ($payment, $log, $dto): void {
                    $code = MapPaymobFailureCode::fromMessage($dto->failureCode);
                    $message = ['en' => __('payments::failures.'.$code, [], 'en'), 'ar' => __('payments::failures.'.$code, [], 'ar')];
                    $this->payments->markFailed($payment, $code, $message);
                    $log->update(['processed_at' => now()]);
                    DB::afterCommit(fn () => event(new PaymentFailed($payment->id, $payment->booking_id, $code, $message)));
                });

                return true;
            });
        };
        try {
            // REFUND callbacks return above after their own Payment → Refund confirmation transaction.
            DB::transaction($rememberWebhook, 3);
        } catch (DuplicateIdempotencyKeyWithDifferentPayloadException $exception) {
            if ($log->processing_error === null) {
                $log->update(['processing_error' => 'duplicate_idempotent_replay']);
            }

            throw $exception;
        } catch (Throwable $exception) {
            // Refresh after rollback; completed attempts can throw from after-commit listeners.
            $log->refresh();
            if ($log->processed_at === null && $log->processing_error === null) {
                $log->update(['processing_error' => 'processing_failed']);
            }

            throw $exception;
        }

        if (! $callbackExecuted) {
            $log->update(['processing_error' => 'duplicate_idempotent_replay']);
        }
    }
}

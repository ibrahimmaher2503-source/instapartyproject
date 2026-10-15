<?php

declare(strict_types=1);

use App\Modules\Payments\Application\Actions\ProcessPaymobWebhookAction;
use App\Modules\Payments\Application\DTOs\PaymobWebhookDto;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Events\PaymentFailed;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Domain\Models\Payment;
use Brick\Money\Money;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('retries a failed webhook without losing committed state or duplicating its event', function (): void {
    $transactionId = 'paymob-failure-retry-transaction';
    $orderId = 'paymob-failure-retry-order';
    $payment = Payment::factory()->create([
        'gateway_ref' => $transactionId,
        'failure_code' => null,
    ]);
    $dto = new PaymobWebhookDto(
        gatewayRef: $transactionId,
        success: false,
        failureCode: 'Insufficient funds',
        capturedAmount: Money::ofMinor(0, 'EGP'),
        rawPayload: [],
        transactionType: 'PURCHASE',
        orderId: $orderId,
    );
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
    $gateway->shouldReceive('parseWebhook')->once()->andReturn($dto);
    $this->app->instance(PaymentGateway::class, $gateway);

    $idempotencyKey = "paymob:PURCHASE:{$orderId}:{$transactionId}:false";
    $failCompletionWrite = true;
    $this->travelTo(now());
    Event::fake([PaymentFailed::class]);
    DB::commit();
    // With the test wrapper committed, use production after-commit semantics at level zero.
    DB::connection()->setTransactionManager(new DatabaseTransactionsManager);
    DB::listen(function (QueryExecuted $query) use (&$failCompletionWrite): void {
        $sql = strtolower(ltrim($query->sql));
        if ($failCompletionWrite
            && str_starts_with($sql, 'update')
            && str_contains($sql, 'idempotency_keys')
            && str_contains($sql, 'response_status')) {
            $failCompletionWrite = false;

            throw new RuntimeException('Deadlock found when trying to get lock; try restarting transaction');
        }
    });

    app(ProcessPaymobWebhookAction::class)->execute([
        'type' => 'transaction_processed',
        'obj' => [
            'id' => $transactionId,
            'order' => ['id' => $orderId],
            'success' => false,
        ],
    ], 'signature');

    expect([
        'payment_status' => $payment->fresh()->status->getValue(),
        'webhook_processed' => GatewayWebhookLog::query()->latest('id')->value('processed_at') !== null,
        'webhook_error' => GatewayWebhookLog::query()->latest('id')->value('processing_error'),
        'idempotency_status' => DB::table('idempotency_keys')->where('key', $idempotencyKey)->value('response_status'),
        'failure_events' => Event::dispatched(PaymentFailed::class)->count(),
    ])->toEqual([
        'payment_status' => 'failed',
        'webhook_processed' => true,
        'webhook_error' => null,
        'idempotency_status' => 200,
        'failure_events' => 1,
    ]);
});

it('keeps a committed failure processed when its after-commit listener throws', function (): void {
    $transactionId = 'paymob-after-commit-failure-transaction';
    $orderId = 'paymob-after-commit-failure-order';
    $payment = Payment::factory()->create([
        'gateway_ref' => $transactionId,
        'failure_code' => null,
    ]);
    $dto = new PaymobWebhookDto(
        gatewayRef: $transactionId,
        success: false,
        failureCode: 'Insufficient funds',
        capturedAmount: Money::ofMinor(0, 'EGP'),
        rawPayload: [],
        transactionType: 'PURCHASE',
        orderId: $orderId,
    );
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
    $gateway->shouldReceive('parseWebhook')->once()->andReturn($dto);
    $this->app->instance(PaymentGateway::class, $gateway);

    $idempotencyKey = "paymob:PURCHASE:{$orderId}:{$transactionId}:false";
    $eventCount = 0;
    Event::forget(PaymentFailed::class);
    Event::listen(PaymentFailed::class, function () use (&$eventCount): void {
        $eventCount++;
        throw new RuntimeException('Simulated after-commit delivery failure');
    });

    DB::commit();
    DB::connection()->setTransactionManager(new DatabaseTransactionsManager);

    expect(fn () => app(ProcessPaymobWebhookAction::class)->execute([
        'type' => 'transaction_processed',
        'obj' => [
            'id' => $transactionId,
            'order' => ['id' => $orderId],
            'success' => false,
        ],
    ], 'signature'))->toThrow(RuntimeException::class, 'Simulated after-commit delivery failure');

    expect([
        'payment_status' => $payment->fresh()->status->getValue(),
        'webhook_processed' => GatewayWebhookLog::query()->latest('id')->value('processed_at') !== null,
        'webhook_error' => GatewayWebhookLog::query()->latest('id')->value('processing_error'),
        'idempotency_status' => DB::table('idempotency_keys')->where('key', $idempotencyKey)->value('response_status'),
        'failure_events' => $eventCount,
    ])->toEqual([
        'payment_status' => 'failed',
        'webhook_processed' => true,
        'webhook_error' => null,
        'idempotency_status' => 200,
        'failure_events' => 1,
    ]);
});

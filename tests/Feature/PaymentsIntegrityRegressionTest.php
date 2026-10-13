<?php

declare(strict_types=1);

use App\Modules\Booking\Database\Factories\BookingFactory;
use App\Modules\Booking\Database\Factories\BookingItemFactory;
use App\Modules\Booking\Database\Factories\BookingVendorFactory;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ConfirmedState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Application\Actions\CapturePaymentAction;
use App\Modules\Payments\Application\Actions\InitiatePaymentAction;
use App\Modules\Payments\Application\Actions\ProcessPaymobWebhookAction;
use App\Modules\Payments\Application\Actions\ProcessRefundAction;
use App\Modules\Payments\Application\DTOs\InitiatePaymentDto;
use App\Modules\Payments\Application\DTOs\PaymobWebhookDto;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Domain\States\PaymentStatus\PendingState;
use App\Modules\Payments\Http\Middleware\IdempotencyKeyMiddleware;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use App\Modules\Payments\Infrastructure\Repositories\EloquentRefundRepository;
use App\Modules\Payments\Infrastructure\Support\MapPaymobFailureCode;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Shared\Application\Services\IdempotencyService;
use Brick\Money\Money;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('hides another customer payment and exposes only the booking public id', function (): void {
    $owner = User::factory()->asCustomer()->create();
    $other = User::factory()->asCustomer()->create();
    $booking = BookingFactory::new()->create(['customer_id' => $owner->id]);
    $payment = Payment::factory()->create(['booking_id' => $booking->id, 'user_id' => $owner->id]);

    Sanctum::actingAs($other);
    $this->getJson('/api/v1/customer/payments/'.$payment->public_id)->assertNotFound();

    Sanctum::actingAs($owner);
    $response = $this->getJson('/api/v1/customer/payments/'.$payment->public_id)->assertOk();

    $response->assertJsonPath('data.booking_public_id', $booking->public_id);
    expect($response->json('data'))->not->toHaveKey('booking_id');
});

it('rejects payment initiation before the gateway for an invalid booking state', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = BookingFactory::new()->draft()->create(['customer_id' => $customer->id]);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('initiate')->never();

    $action = new InitiatePaymentAction(new EloquentPaymentRepository, $gateway);
    $dto = new InitiatePaymentDto(
        bookingId: $booking->id,
        bookingPublicId: $booking->public_id,
        payerId: $customer->id,
        method: PaymentMethod::Card,
        amount: Money::ofMinor(0, 'EGP'),
        idempotencyKey: 'payment-state-regression',
        route: 'payments.initiate',
    );

    expect(fn () => $action->execute($dto))->toThrow(HttpException::class);
});

it('rejects a second pending payment for a confirmed booking', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = BookingFactory::new()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => ConfirmedState::class,
        'payment_status' => UnpaidState::class,
        'total_minor' => 10000,
    ]);
    $bookingVendor = BookingVendorFactory::new()->create(['booking_id' => $booking->id]);
    BookingItemFactory::new()->create(['booking_vendor_id' => $bookingVendor->id]);
    Payment::factory()->create(['booking_id' => $booking->id, 'user_id' => $customer->id, 'status' => PendingState::class]);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('initiate')->never();

    $action = new InitiatePaymentAction(new EloquentPaymentRepository, $gateway);
    $dto = new InitiatePaymentDto(
        bookingId: $booking->id,
        bookingPublicId: $booking->public_id,
        payerId: $customer->id,
        method: PaymentMethod::Card,
        amount: Money::ofMinor(10000, 'EGP'),
        idempotencyKey: 'duplicate-payment-regression',
        route: 'payments.initiate',
    );

    expect(fn () => $action->execute($dto))->toThrow(HttpException::class, 'Booking already has an active payment');
});

it('does not call the gateway when a completed refund is replayed', function (): void {
    $payment = Payment::factory()->captured()->create();
    $refund = Refund::factory()->completed()->create([
        'payment_id' => $payment->id,
        'booking_id' => $payment->booking_id,
        'amount_minor' => $payment->amount_minor,
        'amount_currency' => $payment->amount_currency,
    ]);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('refund')->never();
    $ledger = Mockery::mock(LedgerWriter::class);
    $ledger->shouldReceive('post')->never();

    (new ProcessRefundAction($gateway, app(EloquentRefundRepository::class), $ledger))
        ->execute($refund->id);

    expect($refund->fresh()->status->value)->toBe('completed');
});

it('rejects captured webhook amounts that do not match the local payment', function (): void {
    $payment = Payment::factory()->create(['amount_minor' => 10000, 'amount_currency' => 'EGP']);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
    $gateway->shouldReceive('parseWebhook')->once()->andReturn(new PaymobWebhookDto(
        gatewayRef: $payment->gateway_ref,
        success: true,
        failureCode: null,
        capturedAmount: Money::ofMinor(9999, 'EGP'),
        rawPayload: [],
    ));
    $capture = Mockery::mock(CapturePaymentAction::class);
    $capture->shouldReceive('execute')->never();
    $idempotency = Mockery::mock(IdempotencyService::class);
    $idempotency->shouldReceive('hasBeenUsed')->once()->andReturnFalse();

    (new ProcessPaymobWebhookAction(
        $gateway,
        new EloquentPaymentRepository,
        $capture,
        Mockery::mock(ProcessRefundAction::class),
        $idempotency,
    ))->execute(['type' => 'transaction_processed', 'obj' => ['id' => $payment->gateway_ref]], 'signature');

    expect($payment->fresh()->status->getValue())->toBe('pending')
        ->and($payment->fresh()->capture_ledger_group_id)->toBeNull();
});

it('records invalid signatures with a sanitized reason and payload', function (): void {
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verifyWebhookSignature')->once()->andReturnFalse();

    expect(fn () => (new ProcessPaymobWebhookAction(
        $gateway,
        new EloquentPaymentRepository,
        Mockery::mock(CapturePaymentAction::class),
        Mockery::mock(ProcessRefundAction::class),
        Mockery::mock(IdempotencyService::class),
    ))->execute([
        'type' => 'transaction_processed',
        'obj' => ['pan' => '4111111111111111', 'email' => 'customer@example.test'],
    ], ''))->toThrow(HttpException::class);

    $log = GatewayWebhookLog::query()->latest('id')->firstOrFail();

    expect($log->signature_valid)->toBeFalse()
        ->and($log->processing_error)->toBe('invalid_signature')
        ->and($log->payload['obj']['pan'])->toBe('[REDACTED]')
        ->and($log->payload['obj']['email'])->toBe('[REDACTED]');
});

it('records malformed webhook parsing without leaking exception details', function (): void {
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
    $gateway->shouldReceive('parseWebhook')->once()->andThrow(new InvalidArgumentException('raw payload detail'));

    expect(fn () => (new ProcessPaymobWebhookAction(
        $gateway,
        new EloquentPaymentRepository,
        Mockery::mock(CapturePaymentAction::class),
        Mockery::mock(ProcessRefundAction::class),
        Mockery::mock(IdempotencyService::class),
    ))->execute(['type' => 'transaction_processed', 'obj' => ['id' => 'txn-1']], 'signature'))
        ->toThrow(InvalidArgumentException::class);

    expect(GatewayWebhookLog::query()->latest('id')->value('processing_error'))->toBe('malformed_payload');
});

it('records unknown webhook references with a sanitized error code', function (): void {
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
    $gateway->shouldReceive('parseWebhook')->once()->andReturn(new PaymobWebhookDto(
        gatewayRef: 'unknown-gateway-reference',
        success: false,
        failureCode: 'unknown',
        capturedAmount: Money::ofMinor(0, 'EGP'),
        rawPayload: [],
    ));
    $idempotency = Mockery::mock(IdempotencyService::class);
    $idempotency->shouldReceive('hasBeenUsed')->once()->andReturnFalse();

    (new ProcessPaymobWebhookAction(
        $gateway,
        new EloquentPaymentRepository,
        Mockery::mock(CapturePaymentAction::class),
        Mockery::mock(ProcessRefundAction::class),
        $idempotency,
    ))->execute(['type' => 'transaction_processed', 'obj' => ['id' => 'txn-unknown']], 'signature');

    expect(GatewayWebhookLog::query()->latest('id')->value('processing_error'))->toBe('unknown_gateway_reference');
});

it('normalizes event spelling for sequential webhook idempotency', function (): void {
    $payment = Payment::factory()->create();
    $dto = new PaymobWebhookDto(
        gatewayRef: $payment->gateway_ref,
        success: true,
        failureCode: null,
        capturedAmount: Money::ofMinor($payment->amount_minor, $payment->amount_currency),
        rawPayload: [],
    );
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verifyWebhookSignature')->twice()->andReturnTrue();
    $gateway->shouldReceive('parseWebhook')->twice()->andReturn($dto);
    $capture = Mockery::mock(CapturePaymentAction::class);
    $capture->shouldReceive('execute')->once();
    $idempotency = Mockery::mock(IdempotencyService::class);
    $key = "paymob:PURCHASE::{$payment->gateway_ref}:true";
    $idempotency->shouldReceive('hasBeenUsed')->with('internal_webhook', $key)->twice()->andReturn(false, true);
    $idempotency->shouldReceive('remember')
        ->with('internal_webhook', $key, $key, Mockery::type(Closure::class))
        ->once()
        ->andReturnUsing(fn (string $scope, string $key, string $payload, Closure $callback): mixed => $callback());
    $action = new ProcessPaymobWebhookAction(
        $gateway,
        new EloquentPaymentRepository,
        $capture,
        Mockery::mock(ProcessRefundAction::class),
        $idempotency,
    );

    $action->execute(['type' => 'Transaction', 'obj' => ['id' => $payment->gateway_ref]], 'signature');
    $action->execute(['type' => 'T R A N S A C T I O N', 'obj' => ['id' => $payment->gateway_ref]], 'signature');

    expect(GatewayWebhookLog::query()->latest('id')->value('processing_error'))->toBe('duplicate_idempotent_replay');
});

it('stores the webhook failure code instead of the previous payment value', function (): void {
    $payment = Payment::factory()->create(['failure_code' => 'old_code']);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
    $gateway->shouldReceive('parseWebhook')->once()->andReturn(new PaymobWebhookDto(
        gatewayRef: $payment->gateway_ref,
        success: false,
        failureCode: 'Insufficient funds',
        capturedAmount: Money::ofMinor(0, 'EGP'),
        rawPayload: [],
    ));
    $idempotency = Mockery::mock(IdempotencyService::class);
    $idempotency->shouldReceive('hasBeenUsed')->once()->andReturnFalse();
    $idempotency->shouldReceive('remember')
        ->once()
        ->andReturnUsing(fn (string $scope, string $key, string $payload, Closure $callback): mixed => $callback());

    (new ProcessPaymobWebhookAction(
        $gateway,
        new EloquentPaymentRepository,
        Mockery::mock(CapturePaymentAction::class),
        Mockery::mock(ProcessRefundAction::class),
        $idempotency,
    ))->execute(['type' => 'transaction_processed', 'obj' => ['id' => $payment->gateway_ref]], 'signature');

    expect($payment->fresh()->failure_code)->toBe('insufficient_funds')
        ->and(MapPaymobFailureCode::fromMessage($payment->fresh()->failure_code))->toBe('insufficient_funds');
});

it('confirms a refund callback without sending another gateway refund', function (): void {
    $payment = Payment::factory()->create();
    $transactionId = 'paymob-refund-callback-transaction';
    $gatewayRef = $payment->gateway_ref;
    $refund = Refund::factory()->create([
        'payment_id' => $payment->id,
        'booking_id' => $payment->booking_id,
        'gateway_ref' => $gatewayRef,
        'status' => 'pending',
    ]);
    $orderId = 'paymob-refund-callback-order';
    $outerKey = "paymob:REFUND:confirmation-v2:{$orderId}:{$transactionId}:true";
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
    $gateway->shouldReceive('parseWebhook')->once()->andReturn(new PaymobWebhookDto(
        gatewayRef: $gatewayRef,
        success: true,
        failureCode: null,
        capturedAmount: Money::ofMinor(0, 'EGP'),
        rawPayload: [],
        transactionType: 'REFUND',
        orderId: $orderId,
    ));
    $idempotency = Mockery::mock(IdempotencyService::class);
    $idempotency->shouldReceive('hasBeenUsed')->once()->with('internal_webhook', $outerKey)->andReturnFalse();
    $idempotency->shouldReceive('remember')
        ->with('internal_webhook', $outerKey, $outerKey, Mockery::type(Closure::class))
        ->once()
        ->andReturnUsing(fn (string $scope, string $key, string $payload, Closure $callback): mixed => $callback());
    $processRefund = Mockery::mock(ProcessRefundAction::class);
    $processRefund->shouldReceive('execute')->never();
    $processRefund->shouldReceive('confirmFromWebhook')->once()->with($payment->id, $gatewayRef)->andReturnTrue();

    (new ProcessPaymobWebhookAction(
        $gateway,
        new EloquentPaymentRepository,
        Mockery::mock(CapturePaymentAction::class),
        $processRefund,
        $idempotency,
    ))->execute(['type' => 'transaction_processed', 'obj' => ['id' => $transactionId, 'order' => ['id' => $orderId]]], 'signature');

    expect(GatewayWebhookLog::query()->latest('id')->value('processed_at'))->not->toBeNull();
});

it('defers an unconfirmed refund callback without caching acceptance or dispatching another refund', function (): void {
    $payment = Payment::factory()->create();
    $transactionId = 'paymob-missing-refund-transaction';
    $orderId = 'paymob-missing-refund-order';
    $outerKey = "paymob:REFUND:confirmation-v2:{$orderId}:{$transactionId}:true";
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
    $gateway->shouldReceive('parseWebhook')->once()->andReturn(new PaymobWebhookDto(
        gatewayRef: $payment->gateway_ref,
        success: true,
        failureCode: null,
        capturedAmount: Money::ofMinor(0, 'EGP'),
        rawPayload: [],
        transactionType: 'REFUND',
        orderId: $orderId,
    ));
    $this->app->instance(PaymentGateway::class, $gateway);
    $processRefund = Mockery::mock(ProcessRefundAction::class);
    $processRefund->shouldReceive('execute')->never();
    $processRefund->shouldReceive('confirmFromWebhook')->once()->with($payment->id, $payment->gateway_ref)->andReturnFalse();
    $this->app->instance(ProcessRefundAction::class, $processRefund);

    $response = $this->postJson('/api/v1/webhooks/paymob', [
        'type' => 'transaction_processed',
        'obj' => ['id' => $transactionId, 'order' => ['id' => $orderId]],
    ], ['HMAC' => 'test-signature']);

    expect($response->getStatusCode())->toBe(200)
        ->and(GatewayWebhookLog::query()->latest('id')->value('processing_error'))->toBe('refund_confirmation_deferred')
        ->and(DB::table('idempotency_keys')->where('key', $outerKey)->exists())->toBeFalse()
        ->and(DB::table('idempotency_keys')->where('key', "paymob_refund:{$transactionId}")->exists())->toBeFalse();
});

it('rejects a Paymob webhook key held by another scope before financial effects', function (): void {
    $transactionId = 'paymob-collision-transaction';
    $orderId = 'paymob-collision-order';
    $payment = Payment::factory()->create([
        'gateway_ref' => $transactionId,
        'amount_minor' => 10000,
        'amount_currency' => 'EGP',
    ]);
    $key = "paymob:PURCHASE:{$orderId}:{$transactionId}:true";
    DB::table('idempotency_keys')->insert([
        'key' => $key,
        'scope' => 'internal_action',
        'user_id' => null,
        'route' => 'internal_action:'.$key,
        'request_hash' => hash('sha256', 'other-action-payload'),
        'payload_hash' => hash('sha256', 'other-action-payload'),
        'response_status' => 200,
        'response_body' => json_encode(true),
        'expires_at' => now()->addDay(),
        'created_at' => now(),
        'ttl_seconds' => 86400,
    ]);

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
    $gateway->shouldReceive('parseWebhook')->once()->andReturn(new PaymobWebhookDto(
        gatewayRef: $transactionId,
        success: true,
        failureCode: null,
        capturedAmount: Money::ofMinor(10000, 'EGP'),
        rawPayload: [],
        transactionType: 'PURCHASE',
        orderId: $orderId,
    ));
    $this->app->instance(PaymentGateway::class, $gateway);
    Event::fake([PaymentCaptured::class]);

    $bookingBefore = DB::table('bookings')->where('id', $payment->booking_id)->first();
    $ledgerCountBefore = DB::table('wallet_ledger')->count();
    $response = $this->postJson('/api/v1/webhooks/paymob', [
        'type' => 'transaction_processed',
        'obj' => [
            'id' => $transactionId,
            'order' => ['id' => $orderId],
            'success' => true,
            'amount_cents' => 10000,
            'currency' => 'EGP',
        ],
    ], ['HMAC' => 'test-signature']);

    $payment->refresh();
    $bookingAfter = DB::table('bookings')->where('id', $payment->booking_id)->first();

    expect([
        $payment->status->getValue(),
        $payment->captured_at,
        $payment->capture_ledger_group_id,
        (int) DB::table('wallet_ledger')->count(),
        $bookingAfter->payment_status,
        (int) $bookingAfter->amount_paid_minor,
    ])->toEqual([
        'pending',
        null,
        null,
        (int) $ledgerCountBefore,
        $bookingBefore->payment_status,
        (int) $bookingBefore->amount_paid_minor,
    ]);
    expect($response->getStatusCode())->toBe(409);
    expect(DB::table('idempotency_keys')->where('key', $key)->value('scope'))->toBe('internal_action');
    expect(GatewayWebhookLog::query()->latest('id')->value('processing_error'))->toBe('duplicate_idempotent_replay');
    Event::assertNotDispatched(PaymentCaptured::class);
});

it('rolls back capture when webhook claim completion fails and allows a later retry', function (): void {
    $transactionId = 'paymob-claim-completion-failure';
    $orderId = 'paymob-claim-completion-order';
    $payment = Payment::factory()->create([
        'gateway_ref' => $transactionId,
        'amount_minor' => 10000,
        'amount_currency' => 'EGP',
    ]);
    $dto = new PaymobWebhookDto(
        gatewayRef: $transactionId,
        success: true,
        failureCode: null,
        capturedAmount: Money::ofMinor(10000, 'EGP'),
        rawPayload: [],
        transactionType: 'PURCHASE',
        orderId: $orderId,
    );
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verifyWebhookSignature')->twice()->andReturnTrue();
    $gateway->shouldReceive('parseWebhook')->twice()->andReturn($dto);
    $this->app->instance(PaymentGateway::class, $gateway);
    $action = app(ProcessPaymobWebhookAction::class);
    $idempotencyKey = "paymob:PURCHASE:{$orderId}:{$transactionId}:true";
    $ledgerCountBefore = DB::table('wallet_ledger')->count();
    $failCompletionWrite = true;
    Event::fake([PaymentCaptured::class]);

    DB::listen(function (QueryExecuted $query) use (&$failCompletionWrite): void {
        $sql = strtolower(ltrim($query->sql));
        if ($failCompletionWrite
            && str_starts_with($sql, 'update')
            && str_contains($sql, 'idempotency_keys')
            && str_contains($sql, 'response_status')) {
            $failCompletionWrite = false;

            throw new RuntimeException('Simulated webhook claim completion failure');
        }
    });

    expect(fn () => $action->execute([
        'type' => 'transaction_processed',
        'obj' => [
            'id' => $transactionId,
            'order' => ['id' => $orderId],
            'success' => true,
            'amount_cents' => 10000,
            'currency' => 'EGP',
        ],
    ], 'signature'))->toThrow(RuntimeException::class, 'Simulated webhook claim completion failure');

    expect($payment->fresh()->status->getValue())->toBe('pending')
        ->and($payment->fresh()->capture_ledger_group_id)->toBeNull()
        ->and((int) DB::table('wallet_ledger')->count())->toBe($ledgerCountBefore)
        ->and(DB::table('idempotency_keys')->where('key', $idempotencyKey)->exists())->toBeFalse();
    expect(GatewayWebhookLog::query()->latest('id')->value('processing_error'))->toBe('processing_failed');
    Event::assertNotDispatched(PaymentCaptured::class);

    $action->execute([
        'type' => 'transaction_processed',
        'obj' => [
            'id' => $transactionId,
            'order' => ['id' => $orderId],
            'success' => true,
            'amount_cents' => 10000,
            'currency' => 'EGP',
        ],
    ], 'signature');

    expect($payment->fresh()->status->getValue())->toBe('captured')
        ->and($payment->fresh()->capture_ledger_group_id)->not->toBeNull()
        ->and((int) DB::table('wallet_ledger')->count())->toBe($ledgerCountBefore + 2)
        ->and(DB::table('idempotency_keys')->where('key', $idempotencyKey)->value('response_status'))->toBe(200);
});

it('claims a failure webhook before marking the payment failed during overlapping callbacks', function (): void {
    $transactionId = 'paymob-overlapping-failure-transaction';
    $orderId = 'paymob-overlapping-failure-order';
    $payment = Payment::factory()->create([
        'gateway_ref' => $transactionId,
        'failure_code' => null,
    ]);
    $payload = [
        'type' => 'transaction_processed',
        'obj' => [
            'id' => $transactionId,
            'order' => ['id' => $orderId],
            'success' => false,
            'amount_cents' => $payment->amount_minor,
            'currency' => $payment->amount_currency,
        ],
    ];
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
    $gateway->shouldReceive('verifyWebhookSignature')->twice()->andReturnTrue();
    $gateway->shouldReceive('parseWebhook')->twice()->andReturn($dto);
    $this->app->instance(PaymentGateway::class, $gateway);

    $repository = new class extends EloquentPaymentRepository
    {
        public int $markFailedCalls = 0;

        public ?Closure $beforeMarkFailed = null;

        public function markFailed(Payment $payment, string $code, array $message): void
        {
            $this->markFailedCalls++;
            if ($this->beforeMarkFailed !== null) {
                $callback = $this->beforeMarkFailed;
                $this->beforeMarkFailed = null;
                $callback();
            }

            parent::markFailed($payment, $code, $message);
        }
    };
    $this->app->instance(EloquentPaymentRepository::class, $repository);
    $nestedResponse = null;
    $repository->beforeMarkFailed = function () use ($payload, &$nestedResponse): void {
        $nestedResponse = test()->postJson('/api/v1/webhooks/paymob', $payload, ['HMAC' => 'test-signature']);
    };
    $outerResponse = $this->postJson('/api/v1/webhooks/paymob', $payload, ['HMAC' => 'test-signature']);

    expect($outerResponse->getStatusCode())->toBe(200)
        ->and($nestedResponse?->getStatusCode())->toBe(409)
        ->and($repository->markFailedCalls)->toBe(1)
        ->and(GatewayWebhookLog::query()->orderBy('id')->get()[1]->processing_error)->toBe('duplicate_idempotent_replay')
        ->and($payment->fresh()->failure_code)->toBe('insufficient_funds')
        ->and(DB::table('idempotency_keys')->where('key', "paymob:PURCHASE:{$orderId}:{$transactionId}:false")->value('response_status'))->toBe(200);
});

it('marks a callback as a replay when another failure callback completes after the first usage check', function (): void {
    $transactionId = 'paymob-completed-race-transaction';
    $orderId = 'paymob-completed-race-order';
    $payment = Payment::factory()->create([
        'gateway_ref' => $transactionId,
        'failure_code' => null,
    ]);
    $payload = [
        'type' => 'transaction_processed',
        'obj' => [
            'id' => $transactionId,
            'order' => ['id' => $orderId],
            'success' => false,
            'amount_cents' => $payment->amount_minor,
            'currency' => $payment->amount_currency,
        ],
    ];
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
    $gateway->shouldReceive('verifyWebhookSignature')->twice()->andReturnTrue();
    $gateway->shouldReceive('parseWebhook')->twice()->andReturn($dto);
    $this->app->instance(PaymentGateway::class, $gateway);

    $repository = new class extends EloquentPaymentRepository
    {
        public int $markFailedCalls = 0;

        public ?Closure $afterLookup = null;

        public function findByGatewayRef(string $gateway, string $ref): ?Payment
        {
            $payment = parent::findByGatewayRef($gateway, $ref);
            if ($this->afterLookup !== null) {
                $callback = $this->afterLookup;
                $this->afterLookup = null;
                $callback();
            }

            return $payment;
        }

        public function markFailed(Payment $payment, string $code, array $message): void
        {
            $this->markFailedCalls++;
            parent::markFailed($payment, $code, $message);
        }
    };
    $this->app->instance(EloquentPaymentRepository::class, $repository);
    $nestedResponse = null;
    $repository->afterLookup = function () use ($payload, &$nestedResponse): void {
        $nestedResponse = test()->postJson('/api/v1/webhooks/paymob', $payload, ['HMAC' => 'test-signature']);
    };
    $outerResponse = $this->postJson('/api/v1/webhooks/paymob', $payload, ['HMAC' => 'test-signature']);

    $logs = GatewayWebhookLog::query()->orderBy('id')->get();
    expect($outerResponse->getStatusCode())->toBe(200)
        ->and($nestedResponse?->getStatusCode())->toBe(200)
        ->and($repository->markFailedCalls)->toBe(1)
        ->and($logs[0]->processing_error)->toBe('duplicate_idempotent_replay')
        ->and($logs[1]->processed_at)->not->toBeNull()
        ->and($payment->fresh()->failure_code)->toBe('insufficient_funds');
});

it('scopes HTTP idempotency by actor and route and reserves the first request', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $middleware = app(IdempotencyKeyMiddleware::class);
    Route::post('/api/v1/idempotency-regression', fn () => response()->json(['data' => ['ok' => true]]))
        ->name('payments.idempotency-regression');
    $request = request()->create('/api/v1/idempotency-regression', 'POST', [], [], [], [], '{"x":1}');
    $request->setUserResolver(fn () => $first);
    $request->headers->set('Idempotency-Key', 'scoped-regression-key');
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);

    $calls = 0;
    $response = $middleware->handle($request, function () use (&$calls) {
        $calls++;

        return response()->json(['data' => ['ok' => true]]);
    });

    $secondRequest = request()->create('/api/v1/idempotency-regression', 'POST', [], [], [], [], '{"x":1}');
    $secondRequest->setUserResolver(fn () => $second);
    $secondRequest->headers->set('Idempotency-Key', 'scoped-regression-key');
    $secondRequest->setRouteResolver(fn () => $route);
    $conflict = $middleware->handle($secondRequest, function () use (&$calls) {
        $calls++;

        return response()->json(['data' => ['ok' => true]]);
    });

    $otherRoute = Route::post('/api/v1/idempotency-regression-other', fn () => response()->json(['data' => ['ok' => true]]))
        ->name('payments.idempotency-regression-other');
    $otherRequest = request()->create('/api/v1/idempotency-regression-other', 'POST', [], [], [], [], '{"x":1}');
    $otherRequest->setUserResolver(fn () => $first);
    $otherRequest->headers->set('Idempotency-Key', 'scoped-regression-key');
    $otherRequest->setRouteResolver(fn () => $otherRoute);
    $routeConflict = $middleware->handle($otherRequest, fn () => response()->json(['data' => ['ok' => true]]));

    expect($response->getStatusCode())->toBe(200)
        ->and($conflict->getStatusCode())->toBe(409)
        ->and($routeConflict->getStatusCode())->toBe(409)
        ->and($calls)->toBe(1);
});

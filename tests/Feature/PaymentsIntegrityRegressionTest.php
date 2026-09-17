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
    $idempotency->shouldReceive('remember')->with('internal_webhook', $key, $key, Mockery::type(Closure::class))->once()->andReturnTrue();
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
    $idempotency->shouldReceive('remember')->once()->andReturnTrue();

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

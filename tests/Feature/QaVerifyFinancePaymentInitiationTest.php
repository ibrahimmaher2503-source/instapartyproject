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
use App\Modules\Payments\Domain\Models\PaymentAttempt;
use App\Modules\Payments\Infrastructure\Gateways\PaymobGateway;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use App\Modules\Shared\Application\Services\IdempotencyService;
use Brick\Money\Money;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('does not initiate another gateway order after an uncertain provider failure', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = BookingFactory::new()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => ConfirmedState::class,
        'payment_status' => UnpaidState::class,
        'total_minor' => 10000,
        'total_currency' => 'EGP',
    ]);
    $vendor = BookingVendorFactory::new()->create(['booking_id' => $booking->id]);
    BookingItemFactory::new()->sale()->create([
        'booking_vendor_id' => $vendor->id,
        'unit_price_minor' => 10000,
        'line_total_minor' => 10000,
        'quantity' => 1,
    ]);
    $providerAcceptedRefs = [];
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('initiate')->once()->andReturnUsing(function (InitiatePaymentDto $request, ?string $merchantOrderId) use (&$providerAcceptedRefs): never {
        $providerAcceptedRefs[] = $merchantOrderId;
        throw new RuntimeException('provider accepted the order, then the response timed out');
    });
    $action = new InitiatePaymentAction(new EloquentPaymentRepository, $gateway);
    $dto = new InitiatePaymentDto(
        bookingId: $booking->id,
        bookingPublicId: $booking->public_id,
        payerId: $customer->id,
        method: PaymentMethod::Card,
        amount: Money::ofMinor(10000, 'EGP'),
        idempotencyKey: 'qa-verify-uncertain-payment-initiation',
        route: 'payments.initiate',
    );

    expect(fn () => $action->execute($dto))->toThrow(RuntimeException::class, 'provider accepted the order')
        ->and(Payment::query()->where('booking_id', $booking->id)->count())->toBe(1)
        ->and(PaymentAttempt::query()->whereHas('payment', fn ($query) => $query->where('booking_id', $booking->id))->count())->toBe(1);

    $payment = Payment::query()->where('booking_id', $booking->id)->sole();
    expect($providerAcceptedRefs)->toBe([$payment->gateway_ref])
        ->and($payment->status->getValue())->toBe('pending')
        ->and($payment->attempts()->sole()->response_payload)->toBe(['outcome' => 'unknown'])
        ->and(fn () => $action->execute($dto))->toThrow(HttpException::class);
});

it('does not trust an unsigned callback merchant reference without a provider order binding', function (): void {
    $payment = Payment::factory()->create([
        'gateway' => 'paymob',
        'gateway_ref' => '01K9LATEPAYMOBORDERREF000001',
        'status' => 'pending',
        'amount_minor' => 10000,
        'amount_currency' => 'EGP',
    ]);
    $transactionId = 'late-paymob-transaction';
    $orderId = 'late-paymob-order';
    $payload = [
        'type' => 'TRANSACTION',
        'obj' => [
            'id' => $transactionId,
            'order' => [
                'id' => $orderId,
                'merchant_order_id' => $payment->gateway_ref,
            ],
            'success' => true,
            'amount_cents' => 10000,
            'currency' => 'EGP',
        ],
    ];
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
    $gateway->shouldReceive('resolveMerchantOrderReference')->once()->with($orderId)->andReturnNull();
    $gateway->shouldReceive('parseWebhook')->once()->andReturn(new PaymobWebhookDto(
        gatewayRef: $transactionId,
        success: true,
        failureCode: null,
        capturedAmount: Money::ofMinor(10000, 'EGP'),
        rawPayload: $payload,
        transactionType: 'PURCHASE',
        orderId: $orderId,
    ));
    $capture = Mockery::mock(CapturePaymentAction::class);
    $capture->shouldNotReceive('execute');
    Event::fake();

    (new ProcessPaymobWebhookAction(
        $gateway,
        new EloquentPaymentRepository,
        $capture,
        Mockery::mock(ProcessRefundAction::class),
        app(IdempotencyService::class),
    ))->execute($payload, 'valid-signature');

    expect($payment->fresh()->status->getValue())->toBe('pending')
        ->and($payment->fresh()->metadata['paymob_transaction_id'] ?? null)->toBeNull()
        ->and(GatewayWebhookLog::query()->sole()->processing_error)->toBe('unknown_gateway_reference');
});

it('correlates a late success callback through the authenticated Paymob order inquiry result', function (): void {
    $payment = Payment::factory()->create([
        'gateway' => 'paymob',
        'gateway_ref' => '01K9LATEPAYMOBORDERREF000001',
        'status' => 'pending',
        'amount_minor' => 10000,
        'amount_currency' => 'EGP',
    ]);
    PaymentAttempt::factory()->create([
        'payment_id' => $payment->id,
        'attempt_no' => 1,
        'response_payload' => ['outcome' => 'unknown'],
    ]);
    $transactionId = 'late-paymob-transaction';
    $orderId = '1234567';
    $payload = [
        'type' => 'TRANSACTION',
        'obj' => [
            'id' => $transactionId,
            'order' => [
                'id' => $orderId,
                'merchant_order_id' => 'attacker-selected-reference',
            ],
            'success' => true,
            'amount_cents' => 10000,
            'currency' => 'EGP',
        ],
    ];
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
    $gateway->shouldReceive('parseWebhook')->once()->andReturn(new PaymobWebhookDto(
        gatewayRef: $transactionId,
        success: true,
        failureCode: null,
        capturedAmount: Money::ofMinor(10000, 'EGP'),
        rawPayload: $payload,
        transactionType: 'PURCHASE',
        orderId: $orderId,
    ));
    $gateway->shouldReceive('resolveMerchantOrderReference')->once()->with($orderId)->andReturn($payment->gateway_ref);
    $capture = Mockery::mock(CapturePaymentAction::class);
    $capture->shouldReceive('execute')->once()->withArgs(fn (...$args): bool => $args[0] === $payment->id && $args[2] === "capture:{$payment->id}"
        && $args[3]?->getMinorAmount()->toInt() === 10000
    );
    Event::fake();

    (new ProcessPaymobWebhookAction(
        $gateway,
        new EloquentPaymentRepository,
        $capture,
        Mockery::mock(ProcessRefundAction::class),
        app(IdempotencyService::class),
    ))->execute($payload, 'valid-signature');

    expect($payment->fresh()->metadata['paymob_transaction_id'])->toBe($transactionId)
        ->and(GatewayWebhookLog::query()->sole()->processed_at)->not->toBeNull();
});

it('resolves merchant references from Paymob by authenticated inquiry for the callback order id', function (): void {
    config()->set('services.paymob.api_key', 'test-api-key');
    config()->set('services.paymob.api_url', 'https://paymob.test/api');
    Http::fake([
        'paymob.test/api/auth/tokens' => Http::response(['token' => 'server-auth-token']),
        'paymob.test/api/ecommerce/orders/transaction_inquiry' => Http::response([
            'order' => ['id' => 1234567, 'merchant_order_id' => 'trusted-order-reference'],
        ]),
    ]);

    expect(app(PaymobGateway::class)->resolveMerchantOrderReference('1234567'))->toBe('trusted-order-reference');

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/ecommerce/orders/transaction_inquiry')
        && $request['auth_token'] === 'server-auth-token'
        && $request['order_id'] === '1234567'
    );
});

it('rejects a Paymob inquiry result whose order id does not match the signed callback id', function (): void {
    config()->set('services.paymob.api_key', 'test-api-key');
    config()->set('services.paymob.api_url', 'https://paymob.test/api');
    Http::fake([
        'paymob.test/api/auth/tokens' => Http::response(['token' => 'server-auth-token']),
        'paymob.test/api/ecommerce/orders/transaction_inquiry' => Http::response([
            'order' => ['id' => 7654321, 'merchant_order_id' => 'unrelated-order-reference'],
        ]),
    ]);

    expect(app(PaymobGateway::class)->resolveMerchantOrderReference('1234567'))->toBeNull();
});

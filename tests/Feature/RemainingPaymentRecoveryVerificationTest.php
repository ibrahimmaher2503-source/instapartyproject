<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Listeners\ReleaseInventoryOnPaymentVoidedListener;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Database\Factories\ServiceInventoryReservationFactory;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Application\Actions\MarkPaymentAbandonedAction;
use App\Modules\Payments\Application\Actions\RetryFailedPaymentAction;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Events\PaymentAbandoned;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\PaymentAttempt;
use App\Modules\Payments\Domain\States\PaymentStatus\FailedState;
use App\Modules\Payments\Filament\Pages\PaymentsOpsConsole;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('fails closed when retry is unsupported and preserves the failed payment context', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $payment = Payment::factory()->failed()->create();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldNotReceive('initiate');
    app()->instance(PaymentGateway::class, $gateway);
    Queue::fake();
    $this->actingAs($admin, 'web');
    Livewire::test(PaymentsOpsConsole::class)
        ->assertTableActionDisabled('retry', $payment);

    $failureCode = $payment->failure_code;
    $failureMessage = $payment->failure_message;
    $gatewayReference = $payment->gateway_ref;

    expect(fn () => app(RetryFailedPaymentAction::class)->execute($payment->id, $admin->id))
        ->toThrow(ValidationException::class);
    $retried = $payment->fresh();
    expect($retried->status)->toBeInstanceOf(FailedState::class)
        ->and($retried->gateway_ref)->toBe($gatewayReference)
        ->and($retried->failure_code)->toBe($failureCode)
        ->and($retried->failure_message)->toBe($failureMessage)
        ->and($retried->attempts()->count())->toBe(0)
        ->and(PaymentAttempt::query()->where('payment_id', $payment->id)->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('abandons a failed payment and releases its held inventory under the existing policy', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->create(['customer_id' => $customer->id]);
    $allocation = BookingVendor::factory()->create(['booking_id' => $booking->id]);
    $item = BookingItem::factory()->rental()->create(['booking_vendor_id' => $allocation->id]);
    $payment = Payment::factory()->failed()->create([
        'booking_id' => $booking->id,
        'user_id' => $customer->id,
    ]);
    $reservation = ServiceInventoryReservationFactory::new()->paymentHold()->create([
        'service_id' => $item->service_id,
        'user_id' => $customer->id,
        'product_type' => ProductType::Rental,
        'booking_item_id' => $item->id,
        'status' => 'held',
    ]);

    app(MarkPaymentAbandonedAction::class)->execute($payment->id, $admin->id);
    app(ReleaseInventoryOnPaymentVoidedListener::class)->handle(new PaymentAbandoned(
        $payment->id,
        $booking->id,
        $admin->id,
    ));

    expect($payment->fresh()->status->getValue())->toBe('abandoned')
        ->and($reservation->fresh()->status->value)->toBe('released')
        ->and($reservation->fresh()->release_reason)->toBe('payment_voided_or_abandoned');
});

it('rejects retry replays and refuses abandonment of captured or already abandoned payments', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $pending = Payment::factory()->create();
    $captured = Payment::factory()->captured()->create();
    $abandoned = Payment::factory()->create(['status' => 'abandoned']);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldNotReceive('initiate');
    app()->instance(PaymentGateway::class, $gateway);

    expect(fn () => app(RetryFailedPaymentAction::class)->execute($pending->id, $admin->id))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(MarkPaymentAbandonedAction::class)->execute($captured->id, $admin->id))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(MarkPaymentAbandonedAction::class)->execute($abandoned->id, $admin->id))
        ->toThrow(ValidationException::class)
        ->and($captured->fresh()->status->getValue())->toBe('captured')
        ->and($captured->fresh()->captured_at)->not->toBeNull()
        ->and($pending->fresh()->status->getValue())->toBe('pending')
        ->and(PaymentAttempt::query()->count())->toBe(0);
});

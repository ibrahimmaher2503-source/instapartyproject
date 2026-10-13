<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Listeners\UpdateBookingPaymentStatusListener;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Application\Actions\InitiateRefundAction;
use App\Modules\Payments\Application\Actions\ProcessRefundAction;
use App\Modules\Payments\Application\DTOs\InitiateRefundDto;
use App\Modules\Payments\Application\DTOs\PaymentBookingItemReadDto;
use App\Modules\Payments\Application\DTOs\RefundResultDto;
use App\Modules\Payments\Domain\Contracts\BookingPaymentTotalsReader;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Contracts\PaymentsBookingReader;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Infrastructure\Gateways\PaymobGateway;
use App\Modules\Payments\Infrastructure\Repositories\EloquentRefundRepository;
use App\Modules\Settlement\Application\Actions\ApproveWithdrawalAction;
use App\Modules\Settlement\Application\Actions\CalculateCommissionAction;
use App\Modules\Settlement\Application\Actions\ReconcileCompletedRefundsAction;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Contracts\SettlementBookingReader;
use App\Modules\Settlement\Domain\Contracts\SettlementPaymentReader;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Exceptions\OverRefundAttemptedException;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('rebuilds paid amount on capture and refund replay without double counting or erasing the refund', function (): void {
    $booking = Booking::factory()->create(['total_minor' => 85000, 'amount_paid_minor' => 170000]);
    $payment = Payment::factory()->captured()->create([
        'booking_id' => $booking->id, 'amount_minor' => 85000, 'amount_currency' => 'EGP',
    ]);
    Payment::factory()->create(['booking_id' => $booking->id, 'amount_minor' => 85000]);
    $capture = new PaymentCaptured($payment->id, $booking->id, 85000, 'EGP', now());
    $listener = app(UpdateBookingPaymentStatusListener::class);
    $listener->handle($capture);
    $listener->handle($capture);
    expect($booking->fresh()->amount_paid_minor)->toBe(85000);

    $refund = Refund::factory()->completed()->create([
        'payment_id' => $payment->id, 'booking_id' => $booking->id, 'amount_minor' => 25000,
    ]);
    $event = new RefundCompleted($refund->id, $payment->id, $booking->id, 25000, 'EGP', 'customer_request');
    $listener->handleRefund($event);
    $listener->handleRefund($event);
    $listener->handle($capture);
    expect($booking->fresh()->amount_paid_minor)->toBe(60000)
        ->and($booking->fresh()->payment_status->getValue())->toBe('partially_refunded')
        ->and(app(BookingPaymentTotalsReader::class)->forBooking($booking->id, 'EGP')['net_paid_minor'])->toBe(60000);
});

it('reserves partial then remaining amounts and rejects over-refund before processing for every product type', function (ProductType $type): void {
    $payment = Payment::factory()->captured()->create(['amount_minor' => 10000]);
    $bookingReader = Mockery::mock(PaymentsBookingReader::class);
    $bookingReader->shouldReceive('refundCashBudget')->times(3)->with($payment->booking_id, true)
        ->andReturn(Money::ofMinor(10000, 'EGP'));
    $bookingReader->shouldReceive('itemsFor')->twice()->with($payment->booking_id)->andReturn([
        new PaymentBookingItemReadDto(1, $payment->booking_id, 1, $type, now()->addDays(3), 'pending'),
    ]);
    app()->instance(PaymentsBookingReader::class, $bookingReader);
    $process = Mockery::mock(ProcessRefundAction::class);
    $process->shouldReceive('execute')->twice();
    app()->instance(ProcessRefundAction::class, $process);
    $action = app(InitiateRefundAction::class);
    $first = new InitiateRefundDto($payment->id, $payment->booking_id, $payment->user_id,
        RefundReasonCode::CustomerRequest, ['en' => 'Partial', 'ar' => 'جزئي'], 2500, 'campaign-partial');
    $refund = $action->execute($first);
    expect($refund->amount_minor)->toBe(2500)
        ->and($action->execute($first)->id)->toBe($refund->id);
    $refund->update(['status' => RefundStatus::Completed, 'processed_at' => now()]);
    $remaining = $action->execute(new InitiateRefundDto($payment->id, $payment->booking_id, $payment->user_id,
        RefundReasonCode::CustomerRequest, ['en' => 'Remaining', 'ar' => 'المتبقي'], null, 'campaign-remaining'));
    expect($remaining->amount_minor)->toBe(7500);
    $remaining->update(['status' => RefundStatus::Completed, 'processed_at' => now()]);
    expect(fn () => $action->execute(new InitiateRefundDto($payment->id, $payment->booking_id, $payment->user_id,
        RefundReasonCode::CustomerRequest, ['en' => 'Over cap', 'ar' => 'يتجاوز الحد'], 1, 'campaign-over')))
        ->toThrow(OverRefundAttemptedException::class);
    expect(Refund::query()->where('payment_id', $payment->id)->count())->toBe(2);
})->with([ProductType::Rental, ProductType::Sale, ProductType::Digital]);

it('keeps an ambiguous gateway operation reserved and never redispatches its processing row', function (): void {
    $payment = Payment::factory()->captured()->create();
    $refund = Refund::factory()->create([
        'payment_id' => $payment->id, 'booking_id' => $payment->booking_id, 'amount_minor' => 1000,
    ]);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('refund')->once()->andReturnUsing(function () use ($refund): RefundResultDto {
        expect($refund->fresh()->status)->toBe(RefundStatus::Processing);

        return new RefundResultDto(false, null, 'Unknown outcome', true);
    });
    $ledger = Mockery::mock(LedgerWriter::class);
    $ledger->shouldReceive('post')->never();
    $processor = new ProcessRefundAction($gateway, app(EloquentRefundRepository::class), $ledger);
    $processor->execute($refund->id);
    $processor->execute($refund->id);
    expect($refund->fresh()->status)->toBe(RefundStatus::Processing)
        ->and($payment->fresh()->status->getValue())->toBe('captured');
});

it('does not overwrite a persisted withdrawal request timestamp from an incoming dirty model', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $withdrawal = Withdrawal::factory()->create(['requested_at' => '2026-05-22 16:49:00']);
    $withdrawal->requested_at = '2026-10-12 16:49:00';
    $approved = app(ApproveWithdrawalAction::class)->execute($withdrawal, $admin);
    expect($approved->requested_at->format('Y-m-d H:i:s'))->toBe('2026-05-22 16:49:00');
});

it('retains a Paymob refund reservation when the response does not prove the requested amount', function (): void {
    config(['services.paymob.api_url' => 'https://paymob.test/api', 'services.paymob.api_key' => 'test-only-key']);
    Http::fake([
        '*/auth/tokens' => Http::response(['token' => 'test-token']),
        '*/acceptance/void_refund/refund' => Http::response([
            'id' => 501, 'success' => true, 'pending' => false, 'currency' => 'EGP', 'amount_cents' => '2500.5',
        ]),
    ]);
    $payment = Payment::factory()->captured()->create(['metadata' => ['paymob_transaction_id' => '500']]);
    $result = app(PaymobGateway::class)->refund($payment, Money::ofMinor(2500, 'EGP'));
    expect($result->success)->toBeFalse()->and($result->pendingConfirmation)->toBeTrue();
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/void_refund/refund')
        && $request['transaction_id'] === '500' && $request['amount_cents'] === 2500);
});

it('retains Processing after a thrown gateway request so an operational retry cannot send twice', function (): void {
    $payment = Payment::factory()->captured()->create();
    $refund = Refund::factory()->create(['payment_id' => $payment->id, 'booking_id' => $payment->booking_id, 'amount_minor' => 1000]);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('refund')->once()->andThrow(new RuntimeException('Unknown network outcome'));
    $ledger = Mockery::mock(LedgerWriter::class);
    $ledger->shouldReceive('post')->never();
    $action = new ProcessRefundAction($gateway, app(EloquentRefundRepository::class), $ledger);
    expect(fn () => $action->execute($refund->id))->toThrow(RuntimeException::class);
    $action->execute($refund->id);
    expect($refund->fresh()->status)->toBe(RefundStatus::Processing);
});

it('rejects a corrupt stored reservation before any external gateway operation', function (): void {
    $payment = Payment::factory()->captured()->create(['amount_minor' => 1000]);
    $refund = Refund::factory()->create(['payment_id' => $payment->id, 'booking_id' => $payment->booking_id, 'amount_minor' => 1001]);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('refund')->never();
    $ledger = Mockery::mock(LedgerWriter::class);
    $ledger->shouldReceive('post')->never();
    $action = new ProcessRefundAction($gateway, app(EloquentRefundRepository::class), $ledger);
    expect(fn () => $action->execute($refund->id))->toThrow(HttpException::class);
    expect($refund->fresh()->status)->toBe(RefundStatus::Pending);
});

it('confirms an authenticated refund child only against the correct captured parent and exact cumulative amount', function (): void {
    config(['services.paymob.api_url' => 'https://paymob.test/api', 'services.paymob.api_key' => 'test-only-key']);
    Http::fake([
        '*/auth/tokens' => Http::response(['token' => 'test-token']),
        '*/acceptance/transactions/501' => Http::response([
            'id' => 501, 'is_refund' => true, 'parent_transaction' => '500',
            'success' => true, 'pending' => false, 'currency' => 'EGP', 'amount_cents' => 2500,
        ]),
        '*/acceptance/transactions/500' => Http::response([
            'id' => 500, 'currency' => 'EGP', 'is_refunded' => true, 'refunded_amount_cents' => '3500',
        ]),
    ]);
    $payment = Payment::factory()->captured()->create(['metadata' => ['paymob_transaction_id' => '500']]);
    expect(app(PaymobGateway::class)->confirmRefund(
        $payment, '501', Money::ofMinor(2500, 'EGP'), Money::ofMinor(3500, 'EGP'),
    ))->toBeTrue();
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/void_refund/'));
});

it('prints an explicit historical subtotal reconciliation and real identity without inventing tax', function (string $locale): void {
    config(['app.name' => 'InstaParty invoice fixture']);
    $admin = User::factory()->superAdmin()->create();
    $booking = Booking::factory()->create([
        'subtotal_minor' => 85000, 'subtotal_currency' => 'EGP',
        'total_minor' => 85000, 'total_currency' => 'EGP', 'delivery_total_minor' => 0,
        'discount_total_minor' => 0, 'loyalty_redeemed_minor' => 0, 'total_vat_minor' => 0,
    ]);
    $vendor = BookingVendor::factory()->create(['booking_id' => $booking->id, 'subtotal_minor' => 85000, 'delivery_fee_minor' => 0]);
    BookingItem::factory()->create(['booking_vendor_id' => $vendor->id, 'line_total_minor' => 100000, 'line_total_currency' => 'EGP']);
    Payment::factory()->captured()->create(['booking_id' => $booking->id, 'amount_minor' => 85000]);
    $this->actingAs($admin, 'web')
        ->get(route('admin.bookings.invoice', [$booking->public_id, 'lang' => $locale]))
        ->assertOk()->assertSeeText('InstaParty invoice fixture')
        ->assertSeeText($booking->reference_no)
        ->assertSeeText(__('booking.invoice.recorded_subtotal_adjustment', [], $locale))
        ->assertSeeText(Money::ofMinor(-15000, 'EGP')->formatTo($locale))
        ->assertSeeText(__('booking.invoice.tax_context', [], $locale));
})->with(['en', 'ar']);

it('caps service reversals at net service cash across payment rows and retains delivery while excluding platform transport', function (ProductType $type): void {
    $booking = Booking::factory()->create([
        'subtotal_minor' => 10000, 'discount_total_minor' => 1500,
        'delivery_total_minor' => 1500, 'transport_base_minor' => 500,
        'transport_base_currency' => 'EGP', 'total_minor' => 10000,
    ]);
    $allocation = BookingVendor::factory()->create([
        'booking_id' => $booking->id, 'subtotal_minor' => 10000, 'delivery_fee_minor' => 1000,
    ]);
    $item = BookingItem::factory()->create([
        'booking_vendor_id' => $allocation->id, 'line_total_minor' => 10000,
        'product_type' => $type, 'commission_bps' => 1500,
    ]);
    $payment = Payment::factory()->captured()->create(['booking_id' => $booking->id, 'amount_minor' => 10000]);
    $itemSnapshot = app(SettlementBookingReader::class)->findItemById($item->id);
    $paymentSnapshot = app(SettlementPaymentReader::class)->findById($payment->id);
    $commission = app(CalculateCommissionAction::class)->execute($itemSnapshot, $paymentSnapshot);
    Refund::factory()->completed()->create([
        'booking_id' => $booking->id, 'payment_id' => $payment->id, 'amount_minor' => 5000,
    ]);
    app(ReconcileCompletedRefundsAction::class)->execute($payment->id);
    $secondPayment = Payment::factory()->captured()->create(['booking_id' => $booking->id, 'amount_minor' => 10000]);
    Refund::factory()->completed()->create([
        'booking_id' => $booking->id, 'payment_id' => $secondPayment->id, 'amount_minor' => 10000,
    ]);
    app(ReconcileCompletedRefundsAction::class)->execute($secondPayment->id);
    app(ReconcileCompletedRefundsAction::class)->execute($payment->id);
    $clearingReversalMinor = (int) WalletLedgerEntry::query()
        ->where('entry_type', LedgerEntryType::CommissionReversal->value)->where('direction', 'credit')
        ->where('related_entity_type', 'commission')->where('related_entity_id', $commission->id)->sum('amount_minor');
    expect($clearingReversalMinor)->toBe(8500)
        ->and((int) $commission->fresh()->reversed_amount_minor)->toBe(7225)
        ->and($commission->fresh()->status->getValue())->toBe('partially_reversed');
})->with([ProductType::Rental, ProductType::Sale, ProductType::Digital]);

it('shows recorded VAT without inventing an additive tax adjustment to the canonical booking total', function (string $locale): void {
    $admin = User::factory()->superAdmin()->create();
    $booking = Booking::factory()->create([
        'subtotal_minor' => 100000, 'total_minor' => 100000, 'total_vat_minor' => 14000,
        'delivery_total_minor' => 0, 'discount_total_minor' => 0, 'loyalty_redeemed_minor' => 0,
    ]);
    $allocation = BookingVendor::factory()->create(['booking_id' => $booking->id, 'subtotal_minor' => 100000, 'delivery_fee_minor' => 0]);
    BookingItem::factory()->create(['booking_vendor_id' => $allocation->id, 'line_total_minor' => 100000]);
    $this->actingAs($admin, 'web')
        ->get(route('admin.bookings.invoice', [$booking->public_id, 'lang' => $locale]))
        ->assertOk()
        ->assertSeeText(__('booking.invoice.recorded_vat', [], $locale))
        ->assertSeeText(Money::ofMinor(14000, 'EGP')->formatTo($locale))
        ->assertDontSeeText(__('booking.invoice.recorded_total_adjustment', [], $locale));
})->with(['en', 'ar']);

it('blocks a distinct new refund while an unknown outcome remains and allows the exact persisted replay', function (string $locale): void {
    app()->setLocale($locale);
    $payment = Payment::factory()->captured()->create(['amount_minor' => 10000]);
    $reader = Mockery::mock(PaymentsBookingReader::class);
    $reader->shouldReceive('refundCashBudget')->once()->with($payment->booking_id, true)
        ->andReturn(Money::ofMinor(10000, 'EGP'));
    $reader->shouldReceive('itemsFor')->once()->with($payment->booking_id)->andReturn([]);
    app()->instance(PaymentsBookingReader::class, $reader);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('refund')->once()->andReturn(new RefundResultDto(false, null, 'Unknown outcome', true));
    app()->instance(PaymentGateway::class, $gateway);
    $ledger = Mockery::mock(LedgerWriter::class);
    $ledger->shouldReceive('post')->never();
    app()->instance(LedgerWriter::class, $ledger);
    $action = app(InitiateRefundAction::class);
    $dto = new InitiateRefundDto($payment->id, $payment->booking_id, $payment->user_id,
        RefundReasonCode::CustomerRequest, ['en' => 'Partial', 'ar' => 'جزئي'], 2500, 'unknown-first');
    $refund = $action->execute($dto);
    expect($refund->status)->toBe(RefundStatus::Processing)
        ->and($action->execute($dto)->id)->toBe($refund->id);
    try {
        $action->execute(new InitiateRefundDto($payment->id, $payment->booking_id, $payment->user_id,
            RefundReasonCode::CustomerRequest, ['en' => 'Another', 'ar' => 'طلب آخر'], 1000, 'unknown-distinct'));
        $this->fail('A distinct refund must be blocked while the first outcome is unknown.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409)
            ->and($exception->getMessage())->toBe(__('payments::refunds.errors.refund_in_progress'));
    }
    expect(Refund::query()->where('payment_id', $payment->id)->count())->toBe(1);
})->with(['en', 'ar']);

it('reserves one net service cash budget across payments while retaining delivery and informational VAT', function (): void {
    $booking = Booking::factory()->create([
        'subtotal_minor' => 10000, 'discount_total_minor' => 1500,
        'loyalty_redeemed_minor' => 500, 'total_vat_minor' => 1400,
        'delivery_total_minor' => 2000, 'total_minor' => 10000,
    ]);
    $firstPayment = Payment::factory()->captured()->create(['booking_id' => $booking->id, 'amount_minor' => 10000]);
    $secondPayment = Payment::factory()->captured()->create(['booking_id' => $booking->id, 'amount_minor' => 10000]);
    $process = Mockery::mock(ProcessRefundAction::class);
    $process->shouldReceive('execute')->twice();
    app()->instance(ProcessRefundAction::class, $process);
    $action = app(InitiateRefundAction::class);
    expect(app(PaymentsBookingReader::class)->refundCashBudget($booking->id)->getMinorAmount()->toInt())->toBe(8000);
    $first = $action->execute(new InitiateRefundDto($firstPayment->id, $booking->id, $firstPayment->user_id,
        RefundReasonCode::CustomerRequest, ['en' => 'Partial', 'ar' => 'جزئي'], 3000, 'booking-cap-first'));
    $first->update(['status' => RefundStatus::Processing]);
    expect(fn () => $action->execute(new InitiateRefundDto($secondPayment->id, $booking->id, $secondPayment->user_id,
        RefundReasonCode::CustomerRequest, ['en' => 'Over budget', 'ar' => 'يتجاوز الحد'], 5001, 'booking-cap-over')))
        ->toThrow(OverRefundAttemptedException::class);
    $remaining = $action->execute(new InitiateRefundDto($secondPayment->id, $booking->id, $secondPayment->user_id,
        RefundReasonCode::CustomerRequest, ['en' => 'Remaining', 'ar' => 'المتبقي'], null, 'booking-cap-remaining'));
    expect($remaining->amount_minor)->toBe(5000)
        ->and((int) Refund::query()->where('booking_id', $booking->id)->sum('amount_minor'))->toBe(8000);
});

it('uses immutable vendor reversal debits when the commission projection is stale', function (): void {
    $booking = Booking::factory()->create(['subtotal_minor' => 10000, 'total_minor' => 10000]);
    $allocation = BookingVendor::factory()->create(['booking_id' => $booking->id, 'subtotal_minor' => 10000, 'delivery_fee_minor' => 0]);
    $item = BookingItem::factory()->create(['booking_vendor_id' => $allocation->id, 'line_total_minor' => 10000, 'commission_bps' => 1500]);
    $payment = Payment::factory()->captured()->create(['booking_id' => $booking->id, 'amount_minor' => 10000]);
    $commission = app(CalculateCommissionAction::class)->execute(
        app(SettlementBookingReader::class)->findItemById($item->id),
        app(SettlementPaymentReader::class)->findById($payment->id),
    );
    Refund::factory()->completed()->create(['booking_id' => $booking->id, 'payment_id' => $payment->id, 'amount_minor' => 5000]);
    app(ReconcileCompletedRefundsAction::class)->execute($payment->id);
    expect((int) $commission->fresh()->reversed_amount_minor)->toBe(4250);
    DB::table('commissions')->where('id', $commission->id)->update(['reversed_amount_minor' => 0]);
    Refund::factory()->completed()->create(['booking_id' => $booking->id, 'payment_id' => $payment->id, 'amount_minor' => 5000]);
    app(ReconcileCompletedRefundsAction::class)->execute($payment->id);
    expect((int) WalletLedgerEntry::query()->where('entry_type', LedgerEntryType::CommissionReversal->value)
        ->where('direction', 'credit')->where('related_entity_type', 'commission')->where('related_entity_id', $commission->id)
        ->sum('amount_minor'))->toBe(10000)
        ->and((int) $commission->fresh()->reversed_amount_minor)->toBe(8500)
        ->and($commission->fresh()->status->getValue())->toBe('reversed');
});

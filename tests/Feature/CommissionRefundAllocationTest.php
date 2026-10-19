<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\PendingState;
use App\Modules\Settlement\Application\Actions\ReconcileCompletedRefundsAction;
use App\Modules\Settlement\Application\Actions\ReverseCommissionAction;
use App\Modules\Settlement\Application\DTOs\BookingItemSnapshotDto;
use App\Modules\Settlement\Application\DTOs\LedgerTransactionResult;
use App\Modules\Settlement\Application\DTOs\PaymentSnapshotDto;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Application\DTOs\RefundSnapshotDto;
use App\Modules\Settlement\Application\Listeners\ReverseCommissionOnRefundCompletedListener;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Contracts\SettlementBookingReader;
use App\Modules\Settlement\Domain\Contracts\SettlementPaymentReader;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\States\CommissionStatus\CalculatedState;
use App\Modules\Settlement\Domain\States\CommissionStatus\ReversedState;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentCommissionRepository;

it('reconciles completed refunds for the payment from the completion event', function (): void {
    $payment = Payment::factory()->create([
        'amount_minor' => 20_000,
        'status' => PendingState::class,
    ]);

    $reconciliation = Mockery::mock(ReconcileCompletedRefundsAction::class);
    $reconciliation->shouldReceive('execute')->once()->with($payment->id);
    $listener = new ReverseCommissionOnRefundCompletedListener($reconciliation);

    $listener->handle(new RefundCompleted(123, $payment->id, $payment->booking_id, 10_000, 'EGP', 'customer_request'));
});

it('allocates a payment-level refund proportionally across commissions once', function (): void {
    $payment = Payment::factory()->create(['amount_minor' => 20_000, 'status' => PendingState::class]);
    $items = [];

    foreach ([10_000, 10_000] as $grossMinor) {
        $bookingVendor = BookingVendor::factory()->create(['booking_id' => $payment->booking_id]);
        $item = BookingItem::factory()->rental()->create([
            'booking_vendor_id' => $bookingVendor->id,
            'line_total_minor' => $grossMinor,
            'line_total_currency' => 'EGP',
        ]);
        $items[] = new BookingItemSnapshotDto(
            id: $item->id,
            publicId: $item->public_id,
            bookingId: $payment->booking_id,
            vendorProfileId: $bookingVendor->vendor_profile_id,
            categoryId: null,
            productType: ProductType::Rental,
            totalMinor: $grossMinor,
            totalCurrency: 'EGP',
            commissionBps: 1_500,
        );
        Commission::factory()->create([
            'booking_item_id' => $item->id,
            'payment_id' => $payment->id,
            'vendor_profile_id' => $bookingVendor->vendor_profile_id,
            'gross_amount_minor' => $grossMinor,
            'commission_minor' => 1_500,
            'vendor_share_minor' => 8_500,
            'status' => CalculatedState::class,
        ]);
    }

    $paymentSnapshot = new PaymentSnapshotDto(
        id: $payment->id,
        publicId: $payment->public_id,
        bookingId: $payment->booking_id,
        amountMinor: 20_000,
        currency: 'EGP',
        status: PaymentStatus::Pending,
        capturedAt: now(),
    );
    $refund = new RefundSnapshotDto(
        id: 123,
        publicId: '01JREFUND00000000000000000',
        paymentId: $payment->id,
        bookingItemId: null,
        amountMinor: 10_000,
        currency: 'EGP',
        completedAt: now(),
    );
    $paymentReader = Mockery::mock(SettlementPaymentReader::class);
    $paymentReader->shouldReceive('lockForRefundReconciliation')->once()->with($payment->id)->andReturnTrue();
    $paymentReader->shouldReceive('findById')->once()->with($payment->id)->andReturn($paymentSnapshot);
    $paymentReader->shouldReceive('completedRefundsForPayment')->once()->with($payment->id)->andReturn([$refund]);
    $bookingReader = Mockery::mock(SettlementBookingReader::class);
    $bookingReader->shouldReceive('itemsForPayment')->once()->with($payment->booking_id)->andReturn($items);

    $allocations = [];
    $reverse = Mockery::mock(ReverseCommissionAction::class);
    $reverse->shouldReceive('execute')->twice()->andReturnUsing(
        function (Commission $commission, RefundSnapshotDto $allocated) use (&$allocations): Commission {
            $allocations[$commission->id] = $allocated->amountMinor;

            return $commission;
        },
    );

    (new ReconcileCompletedRefundsAction(
        $paymentReader,
        $bookingReader,
        new EloquentCommissionRepository,
        $reverse,
    ))->execute($payment->id);

    expect(array_values($allocations))->toBe([5_000, 5_000])
        ->and(array_sum($allocations))->toBe(10_000);
});

it('caps a commission reversal at the original accrued amounts', function (): void {
    $payment = Payment::factory()->create(['status' => PendingState::class]);
    $commission = Commission::factory()->create([
        'payment_id' => $payment->id,
        'gross_amount_minor' => 10_000,
        'commission_minor' => 1_500,
        'vendor_share_minor' => 8_500,
        'status' => CalculatedState::class,
    ]);

    $ledger = Mockery::mock(LedgerWriter::class);
    $ledger->shouldReceive('post')->once()->withArgs(function (PostLedgerTransactionInput $input): bool {
        $debits = collect($input->entries)
            ->where('direction.value', 'debit')
            ->sum('amountMinor');
        $credits = collect($input->entries)
            ->where('direction.value', 'credit')
            ->sum('amountMinor');

        return $debits === 10_000 && $credits === 10_000;
    })->andReturn(new LedgerTransactionResult(999, '01JLEDGER0000000000000000', false, []));

    $refund = new RefundSnapshotDto(
        id: 456,
        publicId: '01JREFUND00000000000000001',
        paymentId: $payment->id,
        bookingItemId: $commission->booking_item_id,
        amountMinor: 20_000,
        currency: 'EGP',
        completedAt: now(),
    );

    $result = (new ReverseCommissionAction($ledger))->execute($commission, $refund);

    expect($result->reversed_amount_minor)->toBe(8_500)
        ->and($result->status)->toBeInstanceOf(ReversedState::class);
});

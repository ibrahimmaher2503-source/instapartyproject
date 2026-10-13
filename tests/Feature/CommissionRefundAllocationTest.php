<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Domain\States\PaymentStatus\PendingState;
use App\Modules\Settlement\Application\Actions\CalculateCommissionAction;
use App\Modules\Settlement\Application\Actions\ReverseCommissionAction;
use App\Modules\Settlement\Application\DTOs\LedgerTransactionResult;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Application\DTOs\RefundSnapshotDto;
use App\Modules\Settlement\Application\Listeners\ReverseCommissionOnRefundCompletedListener;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Contracts\SettlementBookingReader;
use App\Modules\Settlement\Domain\Contracts\SettlementPaymentReader;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Settlement\Domain\States\CommissionStatus\CalculatedState;
use App\Modules\Settlement\Domain\States\CommissionStatus\ReversedState;

it('allocates a payment-level refund across sibling commissions once through the shared reconciler', function (): void {
    $booking = Booking::factory()->create(['subtotal_minor' => 20000, 'total_minor' => 20000]);
    $allocation = BookingVendor::factory()->create([
        'booking_id' => $booking->id, 'subtotal_minor' => 20000, 'delivery_fee_minor' => 0,
    ]);
    $items = BookingItem::factory()->count(2)->create([
        'booking_vendor_id' => $allocation->id, 'line_total_minor' => 10000, 'commission_bps' => 1500,
    ]);
    $payment = Payment::factory()->captured()->create(['booking_id' => $booking->id, 'amount_minor' => 20000]);
    $bookingReader = app(SettlementBookingReader::class);
    $paymentSnapshot = app(SettlementPaymentReader::class)->findById($payment->id);
    foreach ($items as $item) {
        app(CalculateCommissionAction::class)
            ->execute($bookingReader->findItemById($item->id), $paymentSnapshot);
    }
    $refund = Refund::factory()->completed()->create([
        'booking_id' => $booking->id, 'payment_id' => $payment->id, 'amount_minor' => 10000,
    ]);
    $listener = app(ReverseCommissionOnRefundCompletedListener::class);
    $event = new RefundCompleted($refund->id, $payment->id, $booking->id, 10000, 'EGP', 'customer_request');
    $listener->handle($event);
    $ledgerCount = WalletLedgerEntry::query()->count();
    $listener->handle($event);
    expect(Commission::query()->where('payment_id', $payment->id)->pluck('reversed_amount_minor')->map(fn ($minor) => (int) $minor)->all())
        ->toBe([4250, 4250])
        ->and(WalletLedgerEntry::query()->count())->toBe($ledgerCount);
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

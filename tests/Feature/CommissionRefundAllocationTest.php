<?php

declare(strict_types=1);

use App\Modules\Payments\Domain\Events\RefundCompleted;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\PendingState;
use App\Modules\Settlement\Application\Actions\ReverseCommissionAction;
use App\Modules\Settlement\Application\DTOs\LedgerTransactionResult;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Application\DTOs\RefundSnapshotDto;
use App\Modules\Settlement\Application\Listeners\ReverseCommissionOnRefundCompletedListener;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Contracts\SettlementPaymentReader;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\States\CommissionStatus\CalculatedState;
use App\Modules\Settlement\Domain\States\CommissionStatus\ReversedState;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentCommissionRepository;

it('allocates a payment-level refund across commissions once', function (): void {
    $payment = Payment::factory()->create([
        'amount_minor' => 20_000,
        'status' => PendingState::class,
    ]);

    Commission::factory()->count(2)->create([
        'payment_id' => $payment->id,
        'gross_amount_minor' => 10_000,
        'commission_minor' => 1_500,
        'vendor_share_minor' => 8_500,
        'status' => CalculatedState::class,
    ]);

    $refund = new RefundSnapshotDto(
        id: 123,
        publicId: '01JREFUND00000000000000000',
        paymentId: $payment->id,
        bookingItemId: null,
        amountMinor: 10_000,
        currency: 'EGP',
        completedAt: now(),
    );

    $reader = Mockery::mock(SettlementPaymentReader::class);
    $reader->shouldReceive('findRefundById')->once()->with(123)->andReturn($refund);

    $allocations = [];
    $reverse = Mockery::mock(ReverseCommissionAction::class);
    $reverse->shouldReceive('execute')->twice()->andReturnUsing(
        function (Commission $commission, RefundSnapshotDto $allocated) use (&$allocations): Commission {
            $allocations[$commission->id] = $allocated->amountMinor;

            return $commission;
        },
    );

    $listener = new ReverseCommissionOnRefundCompletedListener(
        $reader,
        new EloquentCommissionRepository,
        $reverse,
    );

    $listener->handle(new RefundCompleted(123, $payment->id, $payment->booking_id, 10_000, 'EGP', 'customer_request'));

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

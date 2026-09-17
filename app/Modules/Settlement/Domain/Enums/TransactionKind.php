<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Enums;

use Filament\Support\Contracts\HasLabel;

enum TransactionKind: string implements HasLabel
{
    case PaymentCapture = 'payment_capture';
    case Refund = 'refund';
    case CommissionAccrual = 'commission_accrual';
    case CommissionReversal = 'commission_reversal';
    case WithdrawalReserve = 'withdrawal_reserve';
    case WithdrawalSettle = 'withdrawal_settle';
    case WithdrawalRejectRelease = 'withdrawal_reject_release';
    case ManualAdjustment = 'manual_adjustment';
    case SuspenseMovement = 'suspense_movement';
    case LegacyBackfill = 'legacy_backfill';

    public function getLabel(): string
    {
        return __('settlement.transaction_kind.'.$this->value);
    }
}

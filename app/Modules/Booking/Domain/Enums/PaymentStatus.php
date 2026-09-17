<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case RefundPending = 'refund_pending';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return __('booking.payment_status.'.$this->value);
    }
}

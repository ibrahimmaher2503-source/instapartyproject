<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\BookingPaymentStatus;

use App\Modules\Booking\Domain\States\BookingPaymentStatus\Transitions\CompleteRefundTransition;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\Transitions\InitiateRefundTransition;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\Transitions\MarkBookingPaidTransition;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\Transitions\RecordPartialPaymentTransition;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\Transitions\RecordPartialRefundTransition;
use App\Modules\Shared\Domain\Contracts\TranslatableState;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class BookingPaymentState extends State implements TranslatableState
{
    public static function label(string $stateName, string $locale): ?string
    {
        $key = "booking::booking.payment_status.{$stateName}";
        $translated = __($key, [], $locale);

        if ($translated !== $key) {
            return $translated;
        }

        // Hard-coded fallback for all known booking payment state names
        return match ($stateName) {
            'unpaid' => $locale === 'ar' ? 'غير مدفوع' : 'Unpaid',
            'partial' => $locale === 'ar' ? 'مدفوع جزئيًا' : 'Partial',
            'paid' => $locale === 'ar' ? 'مدفوع' : 'Paid',
            'refund_pending' => $locale === 'ar' ? 'في انتظار الاسترداد' : 'Refund Pending',
            'partially_refunded' => $locale === 'ar' ? 'تم رد المبلغ جزئيًا' : 'Partially Refunded',
            'refunded' => $locale === 'ar' ? 'مسترد' : 'Refunded',
            default => null,
        };
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(UnpaidState::class)
            ->allowTransition(UnpaidState::class, PartialState::class, RecordPartialPaymentTransition::class)
            ->allowTransition(UnpaidState::class, PaidState::class, MarkBookingPaidTransition::class)
            ->allowTransition(PartialState::class, PaidState::class, MarkBookingPaidTransition::class)
            ->allowTransition(PaidState::class, RefundPendingState::class, InitiateRefundTransition::class)
            ->allowTransition(RefundPendingState::class, PartiallyRefundedState::class, RecordPartialRefundTransition::class)
            ->allowTransition(RefundPendingState::class, RefundedState::class, CompleteRefundTransition::class)
            ->allowTransition(PartiallyRefundedState::class, RefundedState::class, CompleteRefundTransition::class);
    }
}

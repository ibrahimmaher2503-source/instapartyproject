<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\BookingLifecycleStatus;

use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\ActivateBookingTransition;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\CancelActiveBookingTransition;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\CancelFromCustomerReviewTransition;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\CancelFromVendorReviewTransition;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\CompleteBookingTransition;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\ConfirmBookingTransition;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\MoveToCustomerReviewTransition;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\SendToVendorReviewTransition;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\SubmitBookingTransition;
use App\Modules\Shared\Domain\Contracts\TranslatableState;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class BookingLifecycleState extends State implements TranslatableState
{
    public static function label(string $stateName, string $locale): ?string
    {
        $key = "booking::booking.lifecycle_status.{$stateName}";
        $translated = __($key, [], $locale);

        if ($translated !== $key) {
            return $translated;
        }

        // Hard-coded fallback for all known lifecycle state names
        return match ($stateName) {
            'draft' => $locale === 'ar' ? 'مسودة' : 'Draft',
            'submitted' => $locale === 'ar' ? 'تم الإرسال' : 'Submitted',
            'vendor_review' => $locale === 'ar' ? 'قيد مراجعة المورد' : 'Pending Vendor Review',
            'customer_review' => $locale === 'ar' ? 'قيد مراجعة العميل' : 'Pending Customer Review',
            'confirmed' => $locale === 'ar' ? 'مؤكد' : 'Confirmed',
            'active' => $locale === 'ar' ? 'نشط' : 'Active',
            'completed' => $locale === 'ar' ? 'مكتمل' : 'Completed',
            'cancelled' => $locale === 'ar' ? 'ملغي' : 'Cancelled',
            default => null,
        };
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftState::class)
            ->allowTransition(DraftState::class, SubmittedState::class, SubmitBookingTransition::class)
            ->allowTransition(SubmittedState::class, VendorReviewState::class, SendToVendorReviewTransition::class)
            ->allowTransition(VendorReviewState::class, CustomerReviewState::class, MoveToCustomerReviewTransition::class)
            ->allowTransition(VendorReviewState::class, ConfirmedState::class, ConfirmBookingTransition::class)
            ->allowTransition(VendorReviewState::class, CancelledState::class, CancelFromVendorReviewTransition::class)
            ->allowTransition(CustomerReviewState::class, ConfirmedState::class, ConfirmBookingTransition::class)
            ->allowTransition(CustomerReviewState::class, CancelledState::class, CancelFromCustomerReviewTransition::class)
            ->allowTransition(ConfirmedState::class, ActiveState::class, ActivateBookingTransition::class)
            ->allowTransition(ActiveState::class, CompletedState::class, CompleteBookingTransition::class)
            ->allowTransition(ActiveState::class, CancelledState::class, CancelActiveBookingTransition::class);
    }
}

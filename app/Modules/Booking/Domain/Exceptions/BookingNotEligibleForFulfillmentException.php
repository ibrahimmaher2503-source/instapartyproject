<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exceptions;

use RuntimeException;

class BookingNotEligibleForFulfillmentException extends RuntimeException
{
    public const CODE_CANCELLED = 'fulfillment.booking_cancelled';

    public const CODE_REFUNDED = 'fulfillment.booking_refunded';

    public const CODE_NOT_ACCEPTED = 'fulfillment.booking_not_accepted';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : 'Booking is not eligible for fulfillment.');
    }

    public static function cancelled(): self
    {
        return new self(self::CODE_CANCELLED, 'Booking has been cancelled.');
    }

    public static function refunded(): self
    {
        return new self(self::CODE_REFUNDED, 'Booking has been refunded.');
    }

    public static function notAccepted(): self
    {
        return new self(self::CODE_NOT_ACCEPTED, __('booking::booking.errors.vendor_not_accepted'));
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Events;

use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Events\AuditableEvent;
use Illuminate\Database\Eloquent\Model;

final readonly class BookingVendorCompleted implements AuditableEvent
{
    public function __construct(
        public BookingVendor $bookingVendor,
        public User $actor,
    ) {}

    public function auditable(): Model
    {
        return $this->bookingVendor;
    }

    public function actor(): ?User
    {
        return $this->actor;
    }

    public function action(): string
    {
        return 'booking.vendor.completed';
    }

    public function changes(): array
    {
        return [
            'booking_vendor_public_id' => $this->bookingVendor->public_id,
            'vendor_profile_id' => $this->bookingVendor->vendor_profile_id,
        ];
    }
}

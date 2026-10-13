<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Repositories;

use App\Modules\Booking\Domain\Contracts\BookingReferenceReader;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CompletedState;

final class EloquentBookingReferenceReader implements BookingReferenceReader
{
    /** @var array<int, string|null> */
    private array $referencesById = [];

    public function referenceNoFor(int $bookingId): ?string
    {
        if (! array_key_exists($bookingId, $this->referencesById)) {
            $this->referencesById[$bookingId] = Booking::query()
                ->whereKey($bookingId)
                ->value('reference_no');
        }

        return $this->referencesById[$bookingId];
    }

    public function isVendorRequestPending(string $bookingVendorPublicId, int $vendorUserId): bool
    {
        return BookingVendor::query()
            ->where('public_id', $bookingVendorPublicId)
            ->where('sub_status', VendorSubStatus::Pending->value)
            ->where(fn ($query) => $query->whereNull('response_deadline')->orWhere('response_deadline', '>', now()))
            ->whereHas('vendor', fn ($query) => $query->where('user_id', $vendorUserId))
            ->whereHas('booking', fn ($query) => $query->whereNotIn('lifecycle_status', [CancelledState::$name, CompletedState::$name]))
            ->exists();
    }
}

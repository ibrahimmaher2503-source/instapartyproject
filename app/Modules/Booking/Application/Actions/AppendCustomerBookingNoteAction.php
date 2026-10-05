<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Models\Booking;
use Illuminate\Support\Facades\DB;

class AppendCustomerBookingNoteAction
{
    public function execute(Booking $booking, int $customerId, ?string $body, string $locale): void
    {
        if (! filled($body)) {
            return;
        }
        DB::transaction(function () use ($booking, $customerId, $body, $locale): void {
            $owned = Booking::query()->whereKey($booking->id)->where('customer_id', $customerId)->lockForUpdate()->firstOrFail();
            if ($owned->customerNotes()->value('body') === $body) {
                return;
            }
            $owned->customerNotes()->create([
                'user_id' => $customerId,
                'body' => $body,
                'detected_locale' => $locale === 'ar' ? 'ar' : 'en',
            ]);
        });
    }
}

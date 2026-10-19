<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Repositories;

use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Settlement\Domain\Contracts\BookingPayoutWriter;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;

final class EloquentBookingPayoutWriter implements BookingPayoutWriter
{
    /** @param array<int, int> $persistedCommissions */
    public function recordCommission(int $bookingItemId, int $commissionMinor, string $currency, array $persistedCommissions = []): void
    {
        DB::transaction(function () use ($bookingItemId, $commissionMinor, $currency, $persistedCommissions): void {
            $item = BookingItem::query()->findOrFail($bookingItemId);
            $allocation = BookingVendor::query()->lockForUpdate()->findOrFail($item->booking_vendor_id);
            abort_unless($allocation->subtotal_currency === $currency && $item->line_total_currency === $currency, 409);
            $item->update(['commission_minor' => $commissionMinor, 'commission_currency' => $currency]);
            $commission = Money::ofMinor(0, $currency);
            $gross = Money::ofMinor(0, $currency);
            foreach ($allocation->items()->lockForUpdate()->get() as $allocatedItem) {
                if (array_key_exists($allocatedItem->id, $persistedCommissions)) {
                    $allocatedItem->update(['commission_minor' => $persistedCommissions[$allocatedItem->id], 'commission_currency' => $currency]);
                }
                $commission = $commission->plus(Money::ofMinor((int) $allocatedItem->commission_minor, $allocatedItem->commission_currency));
                $gross = $gross->plus(Money::ofMinor((int) $allocatedItem->line_total_minor, $allocatedItem->line_total_currency));
            }
            $payout = $gross->minus($commission)->plus(Money::ofMinor((int) $allocation->delivery_fee_minor, $allocation->delivery_fee_currency));
            $allocation->update([
                'commission_minor' => $commission->getMinorAmount()->toInt(),
                'commission_currency' => $currency,
                'vendor_payout_minor' => $payout->getMinorAmount()->toInt(),
                'vendor_payout_currency' => $currency,
            ]);
        });
    }
}

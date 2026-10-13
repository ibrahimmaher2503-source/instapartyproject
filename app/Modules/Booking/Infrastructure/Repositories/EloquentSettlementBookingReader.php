<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Repositories;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Settlement\Application\DTOs\BookingItemSnapshotDto;
use App\Modules\Settlement\Domain\Contracts\SettlementBookingReader;
use Brick\Money\Money;
use DomainException;
use Illuminate\Support\Facades\DB;

class EloquentSettlementBookingReader implements SettlementBookingReader
{
    public function lockForRefundReconciliation(int $bookingId): bool
    {
        return DB::table('bookings')->where('id', $bookingId)->lockForUpdate()->first() !== null;
    }

    public function findItemById(int $id): ?BookingItemSnapshotDto
    {
        $row = DB::table('booking_items')
            ->join('booking_vendors', 'booking_vendors.id', '=', 'booking_items.booking_vendor_id')
            ->join('bookings', 'bookings.id', '=', 'booking_vendors.booking_id')
            ->where('booking_items.id', $id)
            ->select([
                'booking_items.id',
                'booking_vendors.id as booking_vendor_id',
                'booking_vendors.delivery_fee_minor',
                'booking_vendors.delivery_fee_currency',
                'booking_items.public_id',
                'booking_vendors.booking_id',
                'booking_vendors.vendor_profile_id',
                'booking_items.product_type',
                'booking_items.line_total_minor',
                'booking_items.line_total_currency',
                'booking_items.commission_bps',
                'bookings.subtotal_minor as booking_subtotal_minor',
                'bookings.subtotal_currency as booking_subtotal_currency',
                'bookings.discount_total_minor',
                'bookings.discount_total_currency',
                'bookings.loyalty_redeemed_minor',
                'bookings.loyalty_redeemed_currency',
            ])
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->rowToDto($row);
    }

    /**
     * @return BookingItemSnapshotDto[]
     */
    public function itemsForPayment(int $bookingId): array
    {
        $rows = DB::table('booking_items')
            ->join('booking_vendors', 'booking_vendors.id', '=', 'booking_items.booking_vendor_id')
            ->join('bookings', 'bookings.id', '=', 'booking_vendors.booking_id')
            ->where('booking_vendors.booking_id', $bookingId)
            ->select([
                'booking_items.id',
                'booking_vendors.id as booking_vendor_id',
                'booking_vendors.delivery_fee_minor',
                'booking_vendors.delivery_fee_currency',
                'booking_items.public_id',
                'booking_vendors.booking_id',
                'booking_vendors.vendor_profile_id',
                'booking_items.product_type',
                'booking_items.line_total_minor',
                'booking_items.line_total_currency',
                'booking_items.commission_bps',
                'bookings.subtotal_minor as booking_subtotal_minor',
                'bookings.subtotal_currency as booking_subtotal_currency',
                'bookings.discount_total_minor',
                'bookings.discount_total_currency',
                'bookings.loyalty_redeemed_minor',
                'bookings.loyalty_redeemed_currency',
            ])
            ->get();

        return $rows->map(fn (object $row): BookingItemSnapshotDto => $this->rowToDto($row))->all();
    }

    private function rowToDto(object $row): BookingItemSnapshotDto
    {
        $currency = (string) $row->booking_subtotal_currency;
        foreach ([['discount_total_minor', 'discount_total_currency'], ['loyalty_redeemed_minor', 'loyalty_redeemed_currency']] as [$amount, $moneyCurrency]) {
            if ((int) $row->{$amount} > 0 && (string) $row->{$moneyCurrency} !== $currency) {
                throw new DomainException('booking.currency_mismatch');
            }
        }
        $serviceCash = Money::ofMinor((int) $row->booking_subtotal_minor, $currency)
            ->minus(Money::ofMinor((int) $row->discount_total_minor, $currency))
            ->minus(Money::ofMinor((int) $row->loyalty_redeemed_minor, $currency));

        return new BookingItemSnapshotDto(
            id: (int) $row->id,
            publicId: (string) $row->public_id,
            bookingId: (int) $row->booking_id,
            vendorProfileId: (int) $row->vendor_profile_id,
            categoryId: null, // category_id is not stored on booking_items; resolved at booking time
            productType: ProductType::from((string) $row->product_type),
            totalMinor: (int) $row->line_total_minor,
            totalCurrency: (string) $row->line_total_currency,
            commissionBps: isset($row->commission_bps) ? (int) $row->commission_bps : null,
            bookingVendorId: (int) $row->booking_vendor_id,
            deliveryFeeMinor: (int) $row->delivery_fee_minor,
            deliveryFeeCurrency: (string) $row->delivery_fee_currency,
            bookingServiceCashMinor: Money::max(Money::ofMinor(0, $currency), $serviceCash)->getMinorAmount()->toInt(),
            bookingServiceCashCurrency: $currency,
        );
    }
}

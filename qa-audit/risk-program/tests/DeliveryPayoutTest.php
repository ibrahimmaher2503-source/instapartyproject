<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Settlement\Application\Actions\CalculateCommissionAction;
use App\Modules\Settlement\Application\DTOs\PaymentSnapshotDto;
use App\Modules\Settlement\Domain\Contracts\SettlementBookingReader;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/FinancialPilotTest.php';

final class DeliveryPayoutTest extends FinancialPilotTest
{
    public function test_independent_delivery_payout_is_balanced_once_and_reconciles_legacy_items(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        $booking = Booking::factory()->confirmed()->create();
        $allocation = BookingVendor::factory()->create([
            'booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id,
            'subtotal_minor' => 20004, 'subtotal_currency' => 'EGP',
            'delivery_fee_minor' => 7700, 'delivery_fee_currency' => 'EGP', 'vendor_payout_minor' => 0,
        ]);
        foreach ([10001, 10003] as $minor) {
            BookingItem::factory()->sale()->create([
                'booking_vendor_id' => $allocation->id, 'unit_price_minor' => $minor,
                'line_total_minor' => $minor, 'line_total_currency' => 'EGP', 'commission_bps' => 5000,
                'commission_minor' => 0, 'commission_currency' => 'EGP',
            ]);
        }
        $payment = Payment::factory()->captured()->create(['booking_id' => $booking->id, 'amount_minor' => 27704, 'amount_currency' => 'EGP']);
        $snapshot = new PaymentSnapshotDto($payment->id, $payment->public_id, $booking->id, 27704, 'EGP', PaymentStatus::Captured, Carbon::parse($payment->captured_at));
        $items = app(SettlementBookingReader::class)->itemsForPayment($booking->id);
        foreach ($items as $item) {
            app(CalculateCommissionAction::class)->execute($item, $snapshot);
        }
        $allocation->items()->update(['commission_minor' => 0]);
        $allocation->update(['vendor_payout_minor' => 0]);
        app(CalculateCommissionAction::class)->execute($items[0], $snapshot);
        $group = DB::table('ledger_transaction_groups')->where('idempotency_key', 'vendor_delivery:'.$allocation->id)->first();
        self::assertNotNull($group);
        self::assertSame(1, DB::table('ledger_transaction_groups')->where('idempotency_key', 'vendor_delivery:'.$allocation->id)->count());
        self::assertSame(2, DB::table('wallet_ledger')->where('transaction_group_id', $group->id)->count());
        self::assertSame(7700, (int) DB::table('wallet_ledger')->where('transaction_group_id', $group->id)->where('direction', 'credit')->sum('amount_minor'));
        self::assertSame(7700, (int) DB::table('wallet_ledger')->where('transaction_group_id', $group->id)->where('direction', 'debit')->sum('amount_minor'));
        self::assertSame(17702, $allocation->fresh()->vendor_payout_minor);
        self::assertSame(17702, Wallet::query()->where('owner_type', VendorProfile::class)->where('owner_id', $vendor->id)->value('balance_minor'));
        self::assertSame(2, Commission::query()->where('payment_id', $payment->id)->count());
    }
}

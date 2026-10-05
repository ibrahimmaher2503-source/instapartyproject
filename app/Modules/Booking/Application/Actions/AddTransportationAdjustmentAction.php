<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\Services\BookingTransportPricingService;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AddTransportationAdjustmentAction
{
    public function __construct(
        private readonly BookingTransportPricingService $transportPricing,
        private readonly RecalculateBookingTotalsAction $recalculate,
    ) {}

    public function execute(
        Booking $booking,
        int $userId,
        int $amountMinor,
        string $source,
        ?string $sourceReference = null,
    ): BookingAdjustment {
        if ($amountMinor < 0 || trim($source) === '') {
            throw new InvalidArgumentException('A non-negative amount and auditable source are required.');
        }

        return DB::transaction(function () use ($booking, $userId, $amountMinor, $source, $sourceReference): BookingAdjustment {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->transportPricing->snapshotBaseFee($locked);

            $adjustment = BookingAdjustment::create([
                'public_id' => (string) Str::ulid(),
                'booking_id' => $locked->id,
                'type' => 'transportation',
                'amount_minor' => $amountMinor,
                'currency' => (string) ($locked->total_currency ?: 'EGP'),
                'source' => trim($source),
                'source_reference' => $sourceReference,
                'created_by_user_id' => $userId,
            ]);

            $this->recalculate->execute($locked);

            DB::table('audit_logs')->insert([
                'public_id' => (string) Str::ulid(),
                'auditable_type' => BookingAdjustment::class,
                'auditable_id' => $adjustment->id,
                'user_id' => $userId,
                'action' => 'booking_transportation_adjustment_added',
                'changes' => json_encode([
                    'amount_minor' => $amountMinor,
                    'currency' => $adjustment->currency,
                    'source' => $adjustment->source,
                    'source_reference' => $sourceReference,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            return $adjustment;
        });
    }
}

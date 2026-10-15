<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\Services\BookingTransportPricingService;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

        $sourceReference = $sourceReference === null ? null : trim($sourceReference);
        $sourceReference = $sourceReference === '' ? null : $sourceReference;

        return DB::transaction(function () use ($booking, $userId, $amountMinor, $source, $sourceReference): BookingAdjustment {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $source = trim($source);
            $currency = (string) ($locked->total_currency ?: 'EGP');

            if ($sourceReference !== null) {
                $existing = BookingAdjustment::query()
                    ->where('booking_id', $locked->id)
                    ->where('type', 'transportation')
                    ->where('source_reference', $sourceReference)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if ($existing->amount_minor !== $amountMinor
                        || $existing->currency !== $currency
                        || $existing->source !== $source) {
                        throw ValidationException::withMessages([
                            'source_reference' => __('booking::booking.validation.transport_reference_conflict'),
                        ]);
                    }

                    return $existing;
                }
            }

            $this->transportPricing->snapshotBaseFee($locked);

            $adjustment = BookingAdjustment::create([
                'public_id' => (string) Str::ulid(),
                'booking_id' => $locked->id,
                'type' => 'transportation',
                'amount_minor' => $amountMinor,
                'currency' => (string) ($locked->total_currency ?: 'EGP'),
                'source' => $source,
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

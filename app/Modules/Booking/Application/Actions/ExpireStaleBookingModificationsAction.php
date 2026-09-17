<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Shared\Domain\Models\StateTransition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ExpireStaleBookingModificationsAction
{
    public function execute(?CarbonImmutable $at = null): int
    {
        $cutoff = ($at ?? CarbonImmutable::now('UTC'))->utc();
        $expired = 0;

        BookingModification::query()
            ->where('status', ModificationStatus::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $cutoff)
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use ($cutoff, &$expired): void {
                $didExpire = DB::transaction(function () use ($id, $cutoff): bool {
                    $modification = BookingModification::query()
                        ->whereKey($id)
                        ->lockForUpdate()
                        ->first();

                    if ($modification === null
                        || $modification->status !== ModificationStatus::Pending
                        || $modification->expires_at === null
                        || $modification->expires_at->isAfter($cutoff)) {
                        return false;
                    }

                    $modification->update(['status' => ModificationStatus::Expired]);

                    StateTransition::create([
                        'transitionable_type' => BookingModification::class,
                        'transitionable_id' => $modification->id,
                        'from_state' => ModificationStatus::Pending->value,
                        'to_state' => ModificationStatus::Expired->value,
                        'trigger_kind' => 'system',
                        'context' => [
                            'modification_public_id' => $modification->public_id,
                            'expired_at' => $cutoff->toIso8601String(),
                        ],
                    ]);

                    return true;
                });

                if ($didExpire) {
                    $expired++;
                }
            });

        return $expired;
    }
}

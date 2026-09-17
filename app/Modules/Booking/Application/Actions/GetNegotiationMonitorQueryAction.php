<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class GetNegotiationMonitorQueryAction
{
    public function execute(
        ?string $scope = null,
        ?CarbonImmutable $asOf = null,
        ?Builder $query = null,
    ): Builder {
        $asOf ??= CarbonImmutable::now('UTC');
        $query ??= Booking::query();

        $query->whereIn('lifecycle_status', [
            LifecycleStatus::VendorReview->value,
            LifecycleStatus::CustomerReview->value,
        ]);

        return match ($scope) {
            'late' => $query->whereHas('vendors', fn (Builder $vendors): Builder => $vendors
                ->where('sub_status', VendorSubStatus::Pending->value)
                ->whereNotNull('response_deadline')
                ->where('response_deadline', '<', $asOf)),
            'open' => $query->whereHas('vendors', fn (Builder $vendors): Builder => $vendors
                ->whereIn('sub_status', [VendorSubStatus::Pending->value, VendorSubStatus::Modified->value])),
            default => $query,
        };
    }
}

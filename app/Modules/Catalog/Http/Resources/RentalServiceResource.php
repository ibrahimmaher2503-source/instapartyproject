<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;

/**
 * Rental dimensions are nullable decimal strings in metres (not money).
 * Example rental fragment: {"length_m":"4.50","width_m":"3.25","height_m":"2.00"}.
 * Null means unknown, including historical services; legacy rental fields remain available.
 */
class RentalServiceResource extends ServiceBaseResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $detail = $this->rentalDetail;

        return array_merge($this->summaryWithVendorFields(), $this->translationsBlock(), [
            'rental' => [
                'requires_electricity' => (bool) $detail?->requires_electricity,
                'requires_outdoor_space' => (bool) $detail?->requires_outdoor_space,
                'default_rental_duration_hours' => $detail?->default_rental_duration_hours,
                'setup_time_minutes' => $detail?->setup_time_minutes,
                'teardown_time_minutes' => $detail?->teardown_time_minutes,
                'security_deposit_minor' => $detail?->security_deposit_minor,
                'security_deposit_currency' => $detail?->security_deposit_currency,
                'minimum_space_sqm' => $detail?->minimum_space_sqm,
                'length_m' => $detail?->length_m,
                'width_m' => $detail?->width_m,
                'height_m' => $detail?->height_m,
            ],
        ]);
    }
}

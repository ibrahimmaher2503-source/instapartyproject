<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use Brick\Money\Money;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class BuildServiceEstimateAction
{
    public function __construct(
        private readonly ShowPublishedServiceAction $show,
        private readonly CheckServiceAvailabilityAction $check,
    ) {}

    public function execute(string $publicId, array $input): array
    {
        $service = $this->show->execute($publicId);
        $quantity = (int) ($input['quantity'] ?? 1);
        if ($service->product_type === ProductType::Rental && $quantity !== 1) {
            throw ValidationException::withMessages([
                'quantity' => __('storefront.wizard.validation.rental_quantity_must_be_one'),
            ]);
        }
        $timezone = $input['timezone'];
        $startsAt = empty($input['event_starts_at']) ? null : Carbon::parse($input['event_starts_at'])->utc();
        $endsAt = empty($input['event_ends_at']) ? null : Carbon::parse($input['event_ends_at'])->utc();
        $duration = $service->product_type === ProductType::Rental
            ? (int) ($input['rental_duration_hours'] ?? $service->rentalDetail?->default_rental_duration_hours ?? 1)
            : null;
        if ($service->product_type === ProductType::Rental && $startsAt !== null && $endsAt === null) {
            if ($duration <= 0) {
                throw ValidationException::withMessages(['event_ends_at' => __('catalog::catalog.availability.window_required')]);
            }
            $endsAt = $startsAt->copy()->addHours($duration);
        }

        return [
            'service' => $service,
            'estimate' => Money::ofMinor($service->base_price_minor, $service->base_price_currency)->multipliedBy($quantity),
            'availability' => $service->product_type === ProductType::Rental && $startsAt === null
                ? null : $this->check->execute($service, $startsAt, $endsAt, $quantity),
            'selection' => [
                'quantity' => $quantity,
                'timezone' => $timezone,
                'event_starts_at' => $startsAt?->copy()->setTimezone($timezone)->format('Y-m-d\TH:i'),
                'event_ends_at' => $endsAt?->copy()->setTimezone($timezone)->format('Y-m-d\TH:i'),
                'rental_duration_hours' => $duration,
            ],
        ];
    }
}

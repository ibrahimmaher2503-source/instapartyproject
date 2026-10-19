<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Services\CategoryFieldValidationService;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateRentalServiceAction
{
    private const MATERIAL_FIELDS = ['category_id', 'base_price_minor', 'category_field_values'];

    public function execute(Service $service, VendorProfile $vendorProfile, array $data): Service
    {
        if ($service->vendor_profile_id !== $vendorProfile->id) {
            throw ValidationException::withMessages(['service' => __('catalog.errors.not_owned')]);
        }

        $isMaterialEdit = $service->status instanceof PublishedState
            && collect(self::MATERIAL_FIELDS)->contains(fn (string $field): bool => array_key_exists($field, $data)
                && ($field === 'category_field_values'
                    ? array_filter($data[$field] ?? [], fn ($value): bool => ! blank($value)) != array_filter($service->category_field_values ?? [], fn ($value): bool => ! blank($value))
                    : $data[$field] != $service->{$field}));

        return DB::transaction(function () use ($service, $data, $isMaterialEdit): Service {
            if (array_key_exists('category_field_values', $data)) {
                $data['category_field_values'] = app(CategoryFieldValidationService::class)->validateValues(
                    (int) ($data['category_id'] ?? $service->category_id), $service->product_type, $data['category_field_values'], 'category_field_values.', $service,
                );
            } elseif (isset($data['category_id']) && (int) $data['category_id'] !== $service->category_id) {
                // A legacy caller changing category must not carry stale custom values.
                $data['category_field_values'] = null;
            }
            $serviceData = array_intersect_key($data, array_flip([
                'category_id', 'name', 'short_description', 'long_description', 'base_price_minor',
                'category_field_values',
            ]));

            if ($isMaterialEdit) {
                $serviceData['status'] = 'pending_review';
            }

            if (! empty($serviceData)) {
                $service->update($serviceData);
            }

            $detailData = array_intersect_key($data, array_flip([
                'requires_electricity', 'requires_outdoor_space', 'default_rental_duration_hours',
                'setup_time_minutes', 'teardown_time_minutes', 'security_deposit_minor', 'minimum_space_sqm',
                'length_m', 'width_m', 'height_m',
            ]));

            if (! empty($detailData) && $service->rentalDetail !== null) {
                $service->rentalDetail()->update($detailData);
            }

            return $service->refresh();
        });
    }
}

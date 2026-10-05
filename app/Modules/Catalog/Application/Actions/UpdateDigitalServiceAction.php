<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Services\CategoryFieldValidationService;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateDigitalServiceAction
{
    private const MATERIAL_FIELDS = ['category_id', 'base_price_minor', 'category_field_values'];

    public function execute(Service $service, VendorProfile $vendorProfile, array $data): Service
    {
        abort_unless(config('catalog.digital_services_enabled', true), 404);
        if ($service->vendor_profile_id !== $vendorProfile->id) {
            throw ValidationException::withMessages(['service' => __('catalog.errors.not_owned')]);
        }

        $isMaterialEdit = $service->status instanceof PublishedState
            && count(array_intersect(array_keys($data), self::MATERIAL_FIELDS)) > 0;

        return DB::transaction(function () use ($service, $data, $isMaterialEdit): Service {
            if (array_key_exists('category_field_values', $data)) {
                $data['category_field_values'] = app(CategoryFieldValidationService::class)->validateValues(
                    (int) ($data['category_id'] ?? $service->category_id), $service->product_type, $data['category_field_values'], 'category_field_values.',
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
                'delivery_method', 'has_expiry', 'expiry_days_after_purchase',
                'is_refundable_after_delivery', 'redemption_url_template',
            ]));

            if (! empty($detailData) && $service->digitalDetail !== null) {
                $service->digitalDetail()->update($detailData);
            }

            return $service->refresh();
        });
    }
}

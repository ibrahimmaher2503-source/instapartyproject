<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\Application\DTOs\AddBookingItemDTO;
use App\Modules\Booking\Domain\Contracts\CatalogServiceReader;
use App\Modules\Catalog\Domain\Enums\ProductType;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam service_id string required Published service public ULID.
 * @bodyParam quantity integer required Item quantity. Rental services accept one only.
 * @bodyParam package_public_id string nullable Active package ULID belonging to service_id; server uses its saved price and contents.
 * @bodyParam effective_starts_at string nullable Rental start date.
 * @bodyParam effective_ends_at string nullable Rental end date.
 */
class AddBookingItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'string', 'exists:services,public_id'],
            'quantity' => [
                'required',
                'integer',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_numeric($value) || (int) $value === 1) {
                        return;
                    }

                    $serviceId = $this->input('service_id');
                    $resolvedId = is_string($serviceId) ? app(CatalogServiceReader::class)->resolvePublicId($serviceId) : null;
                    $service = $resolvedId === null ? null : app(CatalogServiceReader::class)->findPublishedById($resolvedId);

                    if ($service?->productType === ProductType::Rental) {
                        $fail(__('storefront.wizard.validation.rental_quantity_must_be_one'));
                    }
                },
            ],
            'effective_starts_at' => ['nullable', 'date'],
            'effective_ends_at' => ['nullable', 'date', 'after:effective_starts_at'],
            'customization_data' => ['nullable', 'array'],
            'package_public_id' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $serviceId = $this->input('service_id');
                    if (is_string($value)
                        && (! is_string($serviceId)
                            || app(CatalogServiceReader::class)->findActivePackageForService($serviceId, $value) === null)) {
                        $fail(__('storefront.wizard.validation.package_invalid'));
                    }
                },
            ],
        ];
    }

    public function toDTO(int $bookingId, int $customerId): AddBookingItemDTO
    {
        $serviceId = app(CatalogServiceReader::class)->resolvePublicId($this->input('service_id'));

        return new AddBookingItemDTO(
            bookingId: $bookingId,
            customerId: $customerId,
            serviceId: (int) $serviceId,
            quantity: (int) $this->input('quantity'),
            effectiveStartsAt: $this->filled('effective_starts_at')
                ? Carbon::parse($this->input('effective_starts_at'))
                : null,
            effectiveEndsAt: $this->filled('effective_ends_at')
                ? Carbon::parse($this->input('effective_ends_at'))
                : null,
            customizationData: $this->input('customization_data'),
            packagePublicId: $this->filled('package_public_id') ? (string) $this->input('package_public_id') : null,
        );
    }
}

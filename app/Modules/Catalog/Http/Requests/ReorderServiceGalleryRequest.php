<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Domain\Models\Service;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Service.gallery reorder — optimistic-locked via expected_version.
 *
 * Per spec 048-media-collections-phase1 contracts/media-reorder.md.
 *
 * @bodyParam collection string required Must be "gallery". Example: gallery
 * @bodyParam order string[] required Media public_ids in new order. Must include every item currently in the collection.
 * @bodyParam expected_version integer required Last-seen gallery_order_version. Example: 7
 */
class ReorderServiceGalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->service();
        if ($service === null) {
            return false;
        }

        $vendor = $this->user()?->vendorProfile;

        return $vendor !== null
            && (int) $vendor->id === (int) $service->vendor_profile_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'collection' => ['required', 'in:gallery'],
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'string', 'ulid'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ];
    }

    public function service(): ?Service
    {
        $value = $this->route('service');

        if ($value instanceof Service) {
            return $value;
        }

        $publicId = (string) $value;
        if ($publicId === '') {
            return null;
        }

        return Service::query()->where('public_id', $publicId)->first();
    }
}

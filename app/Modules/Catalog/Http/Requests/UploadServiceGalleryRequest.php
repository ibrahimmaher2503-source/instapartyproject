<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Shared\Application\DTOs\MediaCollectionConfig;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Service.gallery upload — multipart, multi-file.
 *
 * Per spec 048-media-collections-phase1 contracts/media-upload.md
 * and ADR-0047 §3.
 *
 * @bodyParam collection string required Must be "gallery". Example: gallery
 * @bodyParam files file[] required 1–11 images (jpeg/png/webp), max 5 MB each.
 * @bodyParam custom_properties object Optional custom properties. Not used for gallery. Example: {}
 */
class UploadServiceGalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->service();
        if ($service === null) {
            abort(404);
        }

        $vendor = $this->user()?->vendorProfile;

        return $vendor !== null
            && $vendor->approval_status instanceof ApprovedState
            && (int) $vendor->id === (int) $service->vendor_profile_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $config = MediaCollectionConfig::for(Service::class, 'gallery');

        $existing = $this->service()?->getMedia('gallery')->count() ?? 0;
        $remaining = max(0, $config->maxFiles - $existing);

        return [
            'collection' => ['required', 'in:gallery'],
            'files' => ['required', 'array', 'min:1', "max:{$remaining}"],
            'files.*' => [
                'file',
                'mimetypes:'.implode(',', $config->mimeTypes),
                'max:'.$config->maxSizeKilobytes(),
            ],
            'custom_properties' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.max' => __('shared::media.exceeds_max_files'),
            'files.*.max' => __('shared::media.exceeds_max_size'),
            'files.*.mimetypes' => __('shared::media.unsupported_mime'),
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

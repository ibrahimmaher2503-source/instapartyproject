<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Application\Actions\DeleteServiceGalleryAction;
use App\Modules\Catalog\Application\Actions\ReorderServiceGalleryAction;
use App\Modules\Catalog\Application\Actions\UploadServiceGalleryAction;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Http\Requests\ReorderServiceGalleryRequest;
use App\Modules\Catalog\Http\Requests\UploadServiceGalleryRequest;
use App\Modules\Catalog\Http\Resources\ServiceMediaResource;
use App\Modules\Shared\Application\DTOs\MediaCollectionConfig;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor-side endpoints for Service.gallery media.
 *
 * Per spec 048-media-collections-phase1 contracts/media-upload.md, list.md,
 * reorder.md, delete.md.
 *
 * @group Vendor - Services
 */
class VendorServiceMediaController
{
    public function upload(UploadServiceGalleryRequest $request, UploadServiceGalleryAction $action): JsonResponse
    {
        $service = $request->service();
        $files = (array) $request->file('files', []);

        $uploaded = $action->execute($service, $files);

        return ApiResponse::success(
            data: $uploaded,
            meta: [
                'display_order_version' => (int) $service->fresh()->gallery_order_version,
                'collection' => 'gallery',
            ],
            status: 201,
        );
    }

    public function list(Request $request, Service $service): JsonResponse
    {
        $vendor = $request->user()?->vendorProfile;
        abort_unless($vendor && (int) $vendor->id === (int) $service->vendor_profile_id, 403);

        $config = MediaCollectionConfig::for(Service::class, 'gallery');
        $items = $service->getMedia('gallery');

        return ApiResponse::success(
            data: ServiceMediaResource::collection($items),
            meta: [
                'collection' => 'gallery',
                'count' => $items->count(),
                'max_files' => $config->maxFiles,
                'min_files' => $config->minFilesOnPublish,
                'display_order_version' => (int) $service->gallery_order_version,
            ],
        );
    }

    public function reorder(ReorderServiceGalleryRequest $request, ReorderServiceGalleryAction $action): JsonResponse
    {
        $service = $request->service();

        $result = $action->execute(
            $service,
            (array) $request->validated('order'),
            (int) $request->validated('expected_version'),
        );

        return ApiResponse::success(data: $result['items'], meta: [
            'display_order_version' => $result['display_order_version'],
            'hero_changed' => $result['hero_changed'],
            'cdn_cache_busted' => $result['cdn_cache_busted'],
        ]);
    }

    public function destroy(Request $request, Service $service, string $mediaPublicId, DeleteServiceGalleryAction $action): JsonResponse
    {
        $vendor = $request->user()?->vendorProfile;
        abort_unless($vendor && (int) $vendor->id === (int) $service->vendor_profile_id, 403);

        $result = $action->execute($service, $mediaPublicId);

        return ApiResponse::success(data: null, meta: $result);
    }
}

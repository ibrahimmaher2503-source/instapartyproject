<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Application\Actions\DeleteServiceGalleryAction;
use App\Modules\Catalog\Application\Actions\ReorderServiceGalleryAction;
use App\Modules\Catalog\Application\Actions\UploadServiceGalleryAction;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Http\Resources\ServiceMediaResource;
use App\Modules\Shared\Application\DTOs\MediaCollectionConfig;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin-side parallel of VendorServiceMediaController. Admins can operate
 * on any vendor's service gallery. Per spec contracts/media-*.md and
 * FR-EXT-MED-011.
 *
 * @group Admin
 */
class AdminServiceMediaController
{
    public function upload(Request $request, Service $service, UploadServiceGalleryAction $action): JsonResponse
    {
        $config = MediaCollectionConfig::for(Service::class, 'gallery');
        $existing = $service->getMedia('gallery')->count();
        $remaining = max(0, $config->maxFiles - $existing);

        $validated = $request->validate([
            'collection' => ['required', Rule::in(['gallery'])],
            'files' => ['required', 'array', 'min:1', "max:{$remaining}"],
            'files.*' => [
                'file',
                'mimetypes:'.implode(',', $config->mimeTypes),
                'max:'.$config->maxSizeKilobytes(),
            ],
        ]);

        $uploaded = $action->execute($service, (array) $request->file('files', []));

        return ApiResponse::success(
            data: $uploaded,
            meta: [
                'display_order_version' => (int) $service->fresh()->gallery_order_version,
                'collection' => 'gallery',
            ],
            status: 201,
        );
    }

    public function list(Service $service): JsonResponse
    {
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

    public function reorder(Request $request, Service $service, ReorderServiceGalleryAction $action): JsonResponse
    {
        $data = $request->validate([
            'collection' => ['required', Rule::in(['gallery'])],
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'string', 'ulid'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ]);

        $result = $action->execute(
            $service,
            (array) $data['order'],
            (int) $data['expected_version'],
        );

        return ApiResponse::success(data: $result['items'], meta: [
            'display_order_version' => $result['display_order_version'],
            'hero_changed' => $result['hero_changed'],
            'cdn_cache_busted' => $result['cdn_cache_busted'],
        ]);
    }

    public function destroy(Service $service, string $mediaPublicId, DeleteServiceGalleryAction $action): JsonResponse
    {
        $result = $action->execute($service, $mediaPublicId);

        return ApiResponse::success(data: null, meta: $result);
    }
}

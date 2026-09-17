<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Scalar projection of a Service.gallery media row for upload/list responses.
 *
 * Per spec 048-media-collections-phase1 contracts/media-upload.md +
 * contracts/media-list.md.
 *
 * @response 201 {
 *   "data": [{
 *     "public_id": "01H8X2R9F4PNK8AVMR9YJTGPGY",
 *     "collection": "gallery",
 *     "original_name": "setup.jpg",
 *     "mime_type": "image/jpeg",
 *     "size_bytes": 248932,
 *     "display_order": 0,
 *     "is_hero": true,
 *     "conversions": {
 *       "thumb": "https://cdn.instaparty.app/.../thumb.webp",
 *       "medium": "https://cdn.instaparty.app/.../medium.webp",
 *       "large": "https://cdn.instaparty.app/.../large.webp"
 *     },
 *     "uploaded_at": "2026-05-24T14:03:22Z"
 *   }],
 *   "meta": { "display_order_version": 1 },
 *   "errors": null
 * }
 */
class ServiceMediaResource extends JsonResource
{
    /**
     * @param  Media  $resource
     */
    public function toArray(Request $request): array
    {
        /** @var Media $media */
        $media = $this->resource;

        return [
            'public_id' => (string) $media->public_id,
            'collection' => $media->collection_name,
            'original_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size_bytes' => (int) $media->size,
            'display_order' => $media->order_column,
            'is_hero' => $media->order_column === 0,
            'custom_properties' => $media->custom_properties ?? [],
            'conversions' => [
                'thumb' => $media->getUrl('thumb'),
                'medium' => $media->getUrl('medium'),
                'large' => $media->getUrl('large'),
            ],
            'uploaded_at' => $media->created_at?->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Application\DTOs\MediaCollectionConfig;
use App\Modules\Shared\Domain\Contracts\CdnCacheBuster;
use App\Modules\Shared\Domain\Exceptions\CdnCacheBustFailed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Shared hard-delete + CDN cache-bust. Per ADR-0047 §3.
 */
final class DeleteMediaAction
{
    public function __construct(private readonly CdnCacheBuster $cdn) {}

    /**
     * @return array{deleted_public_id: string, remaining_count: int, cdn_cache_busted: bool, display_order_version: int}
     *
     * @throws ModelNotFoundException if the media is not on this parent+collection
     * @throws CdnCacheBustFailed when CDN purge fails (HTTP layer maps to 503 retryable)
     */
    public function execute(
        Model&HasMedia $parent,
        string $collection,
        string $mediaPublicId,
    ): array {
        $config = MediaCollectionConfig::for($parent::class, $collection);

        [$pathsToPurge, $remaining, $orderVersion] = DB::transaction(function () use ($parent, $collection, $mediaPublicId, $config): array {
            /** @var Media|null $media */
            $media = $parent->media()
                ->where('collection_name', $collection)
                ->where('public_id', $mediaPublicId)
                ->first();

            if ($media === null) {
                throw (new ModelNotFoundException)->setModel(Media::class, [$mediaPublicId]);
            }

            $this->assertDeletable($parent, $collection, $config);

            $paths = $this->collectPathsForPurge($media, $config);

            $media->delete();

            // Force re-fetch — Spatie caches the `media` relation on the parent,
            // and getMedia() would return the deleted row otherwise.
            $parent->unsetRelation('media');

            // Reindex remaining order_columns (close the gap; 0-indexed).
            $remainingMedia = $parent->getMedia($collection);
            $i = 0;
            foreach ($remainingMedia as $m) {
                $m->order_column = $i++;
                $m->saveQuietly();
            }

            $version = $this->bumpOrderVersion($parent, $config);

            return [$paths, $remainingMedia->count(), $version];
        });

        $busted = false;
        if (! $config->isPrivate() && $pathsToPurge !== []) {
            $this->cdn->purge($pathsToPurge);
            $busted = true;
        }

        return [
            'deleted_public_id' => $mediaPublicId,
            'remaining_count' => $remaining,
            'cdn_cache_busted' => $busted,
            'display_order_version' => $orderVersion,
        ];
    }

    /**
     * @return array<string>
     */
    private function collectPathsForPurge(Media $media, MediaCollectionConfig $config): array
    {
        if ($config->isPrivate()) {
            return [];
        }

        // CDN purge needs paths RELATIVE to the bucket root, not local FS paths.
        $paths = [$media->getPathRelativeToRoot()];
        foreach (array_keys($config->conversions) as $convName) {
            try {
                $paths[] = $media->getPathRelativeToRoot((string) $convName);
            } catch (Throwable) {
                // conversion not generated yet — skip
            }
        }

        return array_values(array_filter($paths));
    }

    /**
     * Pre-delete guards per contracts/media-delete.md.
     *
     *   - Private (s3-private) typed documents (`VendorDocument.files`) cannot be
     *     deleted via the API at all; re-upload replaces.
     *   - Published parents with a `min_files_on_publish` constraint must keep
     *     at least that many media in the collection. Caller must unpublish or
     *     upload a replacement first.
     */
    private function assertDeletable(Model $parent, string $collection, MediaCollectionConfig $config): void
    {
        if ($config->isPrivate()) {
            throw $this->validationError('media.delete_only_required', 422);
        }

        if ($config->minFilesOnPublish > 0 && $this->isPublished($parent)) {
            $current = $parent->getMedia($collection)->count();
            if ($current - 1 < $config->minFilesOnPublish) {
                throw $this->validationError('media.delete_below_min_published', 422);
            }
        }
    }

    private function isPublished(Model $parent): bool
    {
        // The parent's state machine drives publish status. We use Spatie ModelStates'
        // `getValue()` convention where present; otherwise look for a boolean flag.
        $status = $parent->status ?? null;

        if (is_object($status) && method_exists($status, 'getValue')) {
            return $status->getValue() === 'published';
        }

        if (is_string($status)) {
            return $status === 'published';
        }

        return (bool) ($parent->is_published ?? false);
    }

    private function validationError(string $code, int $status): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'data' => null,
            'meta' => null,
            'errors' => [['code' => $code, 'message' => (string) __('shared::media.'.$code)]],
        ], $status));
    }

    private function bumpOrderVersion(Model $parent, MediaCollectionConfig $config): int
    {
        if (! $config->reorderable || $config->orderVersionColumn === null) {
            return 0;
        }

        $col = $config->orderVersionColumn;
        $next = (int) ($parent->{$col} ?? 0) + 1;

        $parent->forceFill([$col => $next])->save();

        return $next;
    }
}

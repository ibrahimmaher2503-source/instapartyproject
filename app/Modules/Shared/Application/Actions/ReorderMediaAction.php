<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Application\DTOs\MediaCollectionConfig;
use App\Modules\Shared\Domain\Contracts\CdnCacheBuster;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Throwable;

/**
 * Optimistic-locked reorder. Per ADR-0047 §6.3 / research.md §6.
 *
 * Client sends `expected_version`; mismatch → 409 `media.reorder_conflict`.
 * Missing/extra public_ids → 422 `media.reorder_incomplete` / `media.reorder_unknown_media`.
 */
final class ReorderMediaAction
{
    public function __construct(private readonly CdnCacheBuster $cdn) {}

    /**
     * @param  array<int, string>  $orderPublicIds
     * @return array{items: array<int, array<string, mixed>>, display_order_version: int, hero_changed: bool, cdn_cache_busted: bool}
     */
    public function execute(
        Model&HasMedia $parent,
        string $collection,
        array $orderPublicIds,
        int $expectedVersion,
    ): array {
        $config = MediaCollectionConfig::for($parent::class, $collection);

        if (! $config->reorderable) {
            throw $this->validationError('media.reorder_not_reorderable', 422);
        }

        if ($config->orderVersionColumn === null) {
            throw $this->validationError('media.reorder_not_reorderable', 422);
        }

        [$items, $version, $heroChanged, $heroPathsForPurge] = DB::transaction(function () use ($parent, $collection, $orderPublicIds, $expectedVersion, $config): array {
            // Pessimistic lock the parent row to serialize concurrent reorders.
            $parent->getConnection()->table($parent->getTable())
                ->where($parent->getKeyName(), $parent->getKey())
                ->lockForUpdate()
                ->first();

            $parent->refresh();

            $currentVersion = (int) ($parent->{$config->orderVersionColumn} ?? 0);
            if ($currentVersion !== $expectedVersion) {
                throw $this->validationError('media.reorder_conflict', 409);
            }

            $current = $parent->getMedia($collection);
            $currentIds = $current->pluck('public_id')->all();

            if (count($orderPublicIds) !== count($currentIds)) {
                throw $this->validationError('media.reorder_incomplete', 422);
            }

            if (array_diff($orderPublicIds, $currentIds) !== []) {
                throw $this->validationError('media.reorder_unknown_media', 422);
            }

            $oldHero = $current->first();

            $byPublicId = $current->keyBy('public_id');

            foreach ($orderPublicIds as $i => $publicId) {
                $m = $byPublicId[$publicId];
                $m->order_column = $i;
                $m->saveQuietly();
            }

            $next = $currentVersion + 1;
            $parent->forceFill([$config->orderVersionColumn => $next])->save();

            $parent->refresh();
            $parent->unsetRelation('media'); // force reload after order_column rewrites
            $newHero = $parent->getMedia($collection)->first();

            $heroChanged = $oldHero?->public_id !== $newHero?->public_id;

            $purge = [];
            if ($heroChanged && ! $config->isPrivate()) {
                // Paths RELATIVE to bucket root, not local FS paths, for CDN purge API.
                foreach (array_filter([$oldHero, $newHero]) as $m) {
                    $purge[] = $m->getPathRelativeToRoot();
                    foreach (array_keys($config->conversions) as $conv) {
                        try {
                            $purge[] = $m->getPathRelativeToRoot((string) $conv);
                        } catch (Throwable) {
                            // skip if not generated
                        }
                    }
                }
            }

            $itemsOut = [];
            foreach ($parent->getMedia($collection) as $m) {
                $item = [
                    'public_id' => (string) $m->public_id,
                    'display_order' => $m->order_column,
                    'is_hero' => $m->order_column === 0,
                    'mime_type' => $m->mime_type,
                    'size_bytes' => (int) $m->size,
                ];

                if (! $config->isPrivate()) {
                    $convs = [];
                    foreach (array_keys($config->conversions) as $convName) {
                        $convs[(string) $convName] = $m->getUrl((string) $convName);
                    }
                    $item['conversions'] = $convs;
                }

                $itemsOut[] = $item;
            }

            return [$itemsOut, $next, $heroChanged, array_values(array_filter($purge))];
        });

        $busted = false;
        if ($heroChanged && $heroPathsForPurge !== []) {
            $this->cdn->purge($heroPathsForPurge);
            $busted = true;
        }

        return [
            'items' => $items,
            'display_order_version' => $version,
            'hero_changed' => $heroChanged,
            'cdn_cache_busted' => $busted,
        ];
    }

    private function validationError(string $code, int $status): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'data' => null,
            'meta' => null,
            'errors' => [['code' => $code, 'message' => (string) __('shared::media.'.$code)]],
        ], $status));
    }
}

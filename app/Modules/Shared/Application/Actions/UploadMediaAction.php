<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Application\DTOs\MediaCollectionConfig;
use App\Modules\Shared\Domain\Contracts\SvgSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Shared upload entry-point. Per-entity Actions wrap this with authorization
 * + per-entity context. Per ADR-0047 §3.
 *
 * Behavior:
 *   1. Reads `MediaCollectionConfig::for($parent::class, $collection)`.
 *   2. For each SVG file: runs `SvgSanitizer::sanitize()` and re-streams the cleaned bytes.
 *   3. Inside `DB::transaction`, calls `addMedia(...)->withCustomProperties()->toMediaCollection()`.
 *   4. Increments `{collection}_order_version` on the parent if reorderable.
 *   5. Returns the array of saved media in scalar shape (suitable for API Resource).
 */
final class UploadMediaAction
{
    public function __construct(private readonly SvgSanitizer $svgSanitizer) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<string, mixed>  $customProperties
     * @return array<int, array<string, mixed>> scalar media records
     */
    public function execute(
        Model&HasMedia $parent,
        string $collection,
        array $files,
        array $customProperties = [],
    ): array {
        $config = MediaCollectionConfig::for($parent::class, $collection);

        $tempFiles = [];

        try {
            return DB::transaction(function () use ($parent, $collection, $files, $customProperties, $config, &$tempFiles): array {
                $saved = [];

                foreach ($files as $file) {
                    $cleaned = $this->maybeSanitizeSvg($file, $tempFiles);

                    $adder = $parent
                        ->addMedia($cleaned)
                        ->preservingOriginal()
                        ->usingFileName($file->getClientOriginalName())
                        ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                        ->withCustomProperties($customProperties);

                    $media = $adder->toMediaCollection($collection, $config->disk);

                    $saved[] = $this->toScalar($media, $config);
                }

                // Spatie's addMedia starts order_column at 1; normalize to 0-indexed
                // so `is_hero === order_column === 0` is consistent across upload/reorder/delete.
                $parent->unsetRelation('media');
                $i = 0;
                foreach ($parent->getMedia($collection) as $m) {
                    $m->order_column = $i++;
                    $m->saveQuietly();
                }

                // Re-project saved items with their new 0-indexed display_order.
                $parent->unsetRelation('media');
                $fresh = $parent->getMedia($collection)->keyBy('public_id');
                foreach ($saved as $k => $row) {
                    if (isset($fresh[$row['public_id']])) {
                        $order = $fresh[$row['public_id']]->order_column;
                        $saved[$k]['display_order'] = $order;
                        $saved[$k]['is_hero'] = $order === 0;
                    }
                }

                $this->bumpOrderVersion($parent, $config);

                return $saved;
            });
        } finally {
            foreach ($tempFiles as $tmp) {
                if (is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        }
    }

    /**
     * @param  array<int, string>  $tempFiles  written by reference; caller cleans up.
     */
    private function maybeSanitizeSvg(UploadedFile $file, array &$tempFiles): string
    {
        if ($file->getMimeType() !== 'image/svg+xml') {
            return $file->getRealPath();
        }

        $bytes = (string) file_get_contents($file->getRealPath());
        $cleaned = $this->svgSanitizer->sanitize($bytes);

        $temp = tempnam(sys_get_temp_dir(), 'svg_clean_');
        file_put_contents($temp, $cleaned);
        $tempFiles[] = $temp;

        return $temp;
    }

    private function bumpOrderVersion(Model $parent, MediaCollectionConfig $config): void
    {
        if (! $config->reorderable || $config->orderVersionColumn === null) {
            return;
        }

        $col = $config->orderVersionColumn;

        // Only bump if the column exists on the parent (graceful if rollout not yet applied).
        if (! array_key_exists($col, $parent->getAttributes()) && ! $parent->isFillable($col)) {
            return;
        }

        $parent->forceFill([$col => (int) ($parent->{$col} ?? 0) + 1])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function toScalar(Media $media, MediaCollectionConfig $config): array
    {
        $base = [
            'public_id' => (string) $media->public_id,
            'collection' => $media->collection_name,
            'original_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size_bytes' => (int) $media->size,
            'display_order' => $media->order_column,
            'is_hero' => $media->order_column === 0,
            'custom_properties' => $media->custom_properties ?? [],
            'uploaded_at' => $media->created_at?->toIso8601String(),
        ];

        if (! $config->isPrivate()) {
            $base['conversions'] = $this->conversionUrls($media, $config);
        }

        return $base;
    }

    /**
     * @return array<string, string>
     */
    private function conversionUrls(Media $media, MediaCollectionConfig $config): array
    {
        $out = [];
        foreach (array_keys($config->conversions) as $name) {
            $out[(string) $name] = $media->getUrl((string) $name);
        }

        return $out;
    }
}

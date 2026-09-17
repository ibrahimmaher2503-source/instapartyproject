<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\DTOs;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Immutable, declarative config for one media collection on one model.
 *
 * Hydrated from `Model::$mediaCollections[$name]`. Single source of truth read
 * by Form Requests, Application Actions, Filament fields, and architecture
 * parity tests — preventing drift between layers.
 *
 * Per ADR-0047 §3 and spec 048-media-collections-phase1/data-model.md §3.
 */
final class MediaCollectionConfig
{
    /**
     * @param  array<string>  $mimeTypes  IANA MIME types (e.g., 'image/jpeg').
     * @param  array<string, array<string, mixed>>  $conversions  keyed by conversion name (thumb/medium/large/etc.)
     */
    public function __construct(
        public readonly string $collection,
        public readonly string $trait,
        public readonly string $disk,
        public readonly int $maxFiles,
        public readonly int $minFiles,
        public readonly int $minFilesOnPublish,
        public readonly array $mimeTypes,
        public readonly int $maxSizeBytes,
        public readonly array $conversions,
        public readonly bool $reorderable,
        public readonly bool $imageEditor,
        public readonly bool $auditLogged,
        public readonly ?string $orderVersionColumn,
        public readonly bool $enforceSquareAspect = false,
        public readonly ?array $exactDimensions = null,
    ) {}

    /**
     * Build a DTO from a model's `$mediaCollectionRegistry` static array.
     *
     * Note: we use `$mediaCollectionRegistry` (not `$mediaCollections`) because
     * Spatie's `InteractsWithMedia` already declares a `$mediaCollections`
     * property for its own internal use — PHP rejects the trait composition
     * if a class redefines it with a different type.
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function for(string $modelClass, string $collection): self
    {
        $registry = $modelClass::$mediaCollectionRegistry ?? null;

        if (! is_array($registry) || ! isset($registry[$collection])) {
            throw new InvalidArgumentException(sprintf(
                'No media collection [%s] registered on model [%s].',
                $collection,
                $modelClass,
            ));
        }

        $cfg = $registry[$collection];

        $rawDisk = (string) ($cfg['disk'] ?? 's3-public');
        $disk = match ($rawDisk) {
            's3-public' => config('filesystems.media_disk_public') ?? $rawDisk,
            default => $rawDisk,
        };

        return new self(
            collection: $collection,
            trait: (string) ($cfg['trait'] ?? 'HasPublicGallery'),
            disk: $disk,
            maxFiles: (int) ($cfg['max_files'] ?? 1),
            minFiles: (int) ($cfg['min_files'] ?? 0),
            minFilesOnPublish: (int) ($cfg['min_files_on_publish'] ?? ($cfg['min_files'] ?? 0)),
            mimeTypes: array_values((array) ($cfg['mime_types'] ?? [])),
            maxSizeBytes: (int) ($cfg['max_size_bytes'] ?? (5 * 1024 * 1024)),
            conversions: (array) ($cfg['conversions'] ?? []),
            reorderable: (bool) ($cfg['reorderable'] ?? false),
            imageEditor: (bool) ($cfg['image_editor'] ?? false),
            auditLogged: (bool) ($cfg['audit_logged'] ?? false),
            orderVersionColumn: isset($cfg['order_version_column']) ? (string) $cfg['order_version_column'] : null,
            enforceSquareAspect: (bool) ($cfg['enforce_square_aspect'] ?? false),
            exactDimensions: isset($cfg['exact_dimensions']) ? (array) $cfg['exact_dimensions'] : null,
        );
    }

    /**
     * Build all configs registered on a model, keyed by collection name.
     *
     * @param  class-string<Model>  $modelClass
     * @return array<string, self>
     */
    public static function allFor(string $modelClass): array
    {
        $registry = $modelClass::$mediaCollectionRegistry ?? null;

        if (! is_array($registry)) {
            return [];
        }

        $out = [];
        foreach (array_keys($registry) as $name) {
            $out[$name] = self::for($modelClass, (string) $name);
        }

        return $out;
    }

    public function isPrivate(): bool
    {
        return $this->disk === 's3-private';
    }

    public function maxSizeKilobytes(): int
    {
        return (int) ceil($this->maxSizeBytes / 1024);
    }

    /**
     * Short MIME names for Laravel's `mimes:` rule (e.g., 'jpeg', 'png').
     *
     * @return array<string>
     */
    public function mimeShortNames(): array
    {
        $map = [
            'image/jpeg' => 'jpeg,jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/x-icon' => 'ico',
            'application/pdf' => 'pdf',
        ];

        $out = [];
        foreach ($this->mimeTypes as $mime) {
            $out[] = $map[$mime] ?? str_replace(['image/', 'application/'], '', $mime);
        }

        return array_values(array_unique(explode(',', implode(',', $out))));
    }
}

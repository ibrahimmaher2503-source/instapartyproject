<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Concerns;

use App\Modules\Shared\Application\DTOs\MediaCollectionConfig;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Trait for single-file public collections on `s3-public`.
 *
 * Method names suffixed to avoid conflict with Spatie's `InteractsWithMedia`.
 * Host model overrides `registerMediaCollections()` and calls
 * `$this->registerSingleImageCollections()`.
 *
 * Per ADR-0047 §3.
 *
 * @mixin Model
 * @mixin InteractsWithMedia
 */
trait HasSingleImage
{
    public function registerSingleImageCollections(): void
    {
        foreach (MediaCollectionConfig::allFor(static::class) as $name => $cfg) {
            if ($cfg->trait !== 'HasSingleImage') {
                continue;
            }

            $this->addMediaCollection($name)
                ->singleFile()
                ->useDisk($cfg->disk)
                ->acceptsMimeTypes($cfg->mimeTypes);
        }
    }

    public function registerSingleImageConversions(?Media $media = null): void
    {
        foreach (MediaCollectionConfig::allFor(static::class) as $name => $cfg) {
            if ($cfg->trait !== 'HasSingleImage') {
                continue;
            }

            foreach ($cfg->conversions as $convName => $convCfg) {
                $conv = $this->addMediaConversion((string) $convName)
                    ->performOnCollections($name);

                if (isset($convCfg['width'])) {
                    $conv->width((int) $convCfg['width']);
                }
                if (isset($convCfg['height'])) {
                    $conv->height((int) $convCfg['height']);
                }
                if (isset($convCfg['format'])) {
                    $conv->format((string) $convCfg['format']);
                }
                $conv->nonOptimized();
            }
        }
    }
}

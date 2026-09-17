<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Concerns;

use App\Modules\Shared\Application\DTOs\MediaCollectionConfig;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Trait for multi-file public collections backed by `spatie/laravel-medialibrary` on `s3-public`.
 *
 * Method names are intentionally suffixed (`PublicGalleryCollections`) to avoid
 * conflicting with Spatie's `InteractsWithMedia::registerMediaCollections()`.
 * The host model overrides `registerMediaCollections()` and calls
 * `$this->registerPublicGalleryCollections()` from it. Same pattern for conversions.
 *
 * Per ADR-0047 §3 and spec 048-media-collections-phase1/data-model.md §3.
 *
 * @mixin Model
 * @mixin InteractsWithMedia
 */
trait HasPublicGallery
{
    public function registerPublicGalleryCollections(): void
    {
        foreach (MediaCollectionConfig::allFor(static::class) as $name => $cfg) {
            if ($cfg->trait !== 'HasPublicGallery') {
                continue;
            }

            $collection = $this->addMediaCollection($name)
                ->useDisk($cfg->disk)
                ->acceptsMimeTypes($cfg->mimeTypes);

            if ($cfg->maxFiles > 0) {
                $collection->onlyKeepLatest($cfg->maxFiles);
            }
        }
    }

    public function registerPublicGalleryConversions(?Media $media = null): void
    {
        foreach (MediaCollectionConfig::allFor(static::class) as $name => $cfg) {
            if ($cfg->trait !== 'HasPublicGallery') {
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

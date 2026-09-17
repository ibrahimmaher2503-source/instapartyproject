<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Concerns;

use App\Modules\Shared\Application\DTOs\MediaCollectionConfig;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Trait for private collections on `s3-private`. Access ONLY through
 * `GenerateSignedUrlAction` which audits each request.
 *
 * Method names suffixed to avoid conflict with Spatie's `InteractsWithMedia`.
 * Host model overrides `registerMediaCollections()` and calls
 * `$this->registerPrivateDocumentCollections()`.
 *
 * Per ADR-0047 §3 / §6.1.
 *
 * @mixin Model
 * @mixin InteractsWithMedia
 */
trait HasPrivateDocuments
{
    public function registerPrivateDocumentCollections(): void
    {
        foreach (MediaCollectionConfig::allFor(static::class) as $name => $cfg) {
            if ($cfg->trait !== 'HasPrivateDocuments') {
                continue;
            }

            $collection = $this->addMediaCollection($name)
                ->useDisk($cfg->disk)
                ->acceptsMimeTypes($cfg->mimeTypes);

            if ($cfg->maxFiles === 1) {
                $collection->singleFile();
            }
        }
    }

    public function registerPrivateDocumentConversions(?Media $media = null): void
    {
        foreach (MediaCollectionConfig::allFor(static::class) as $name => $cfg) {
            if ($cfg->trait !== 'HasPrivateDocuments') {
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

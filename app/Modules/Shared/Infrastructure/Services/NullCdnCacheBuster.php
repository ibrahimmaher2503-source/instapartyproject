<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Services;

use App\Modules\Shared\Domain\Contracts\CdnCacheBuster;

/**
 * No-op CdnCacheBuster for local dev (MinIO) and CI where there is no CDN.
 *
 * Bound in `SharedServiceProvider::register()` based on `config('services.spaces.cdn_endpoint_id')`.
 */
final class NullCdnCacheBuster implements CdnCacheBuster
{
    public function purge(array $paths): void
    {
        // intentional no-op
    }
}

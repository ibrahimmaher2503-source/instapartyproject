<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Contracts;

use App\Modules\Shared\Domain\Exceptions\CdnCacheBustFailed;

/**
 * Purges paths on the public CDN.
 *
 * Bound to `SpacesCdnCacheBuster` in production / staging, and to
 * `NullCdnCacheBuster` in local dev (MinIO) and CI. Failure raises
 * `CdnCacheBustFailed`; the caller maps that to HTTP 503 retryable.
 */
interface CdnCacheBuster
{
    /**
     * @param  array<string>  $paths  S3-relative paths (no scheme/host).
     *
     * @throws CdnCacheBustFailed
     */
    public function purge(array $paths): void;
}

<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Contracts;

/**
 * Generates short-lived signed URLs for private disks.
 *
 * Bound to `App\Modules\Shared\Infrastructure\Services\SpacesSignedUrlService` in
 * `SharedServiceProvider::register()`. Implementations MUST NOT log or persist
 * the generated URL anywhere — that responsibility belongs to the caller's audit log.
 */
interface SignedUrlService
{
    /**
     * @return array{url: string, expires_at: string, expires_in_seconds: int}
     */
    public function generate(string $disk, string $path, int $ttlSeconds): array;
}

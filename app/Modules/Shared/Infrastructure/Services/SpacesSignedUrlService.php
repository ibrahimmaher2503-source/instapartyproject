<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Services;

use App\Modules\Shared\Domain\Contracts\SignedUrlService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Flysystem temporaryUrl() against the named disk.
 *
 * Per ADR-0047 §6 and research.md §2. Used for `s3-private` reads only.
 * Never logs or persists the generated URL.
 */
final class SpacesSignedUrlService implements SignedUrlService
{
    public function generate(string $disk, string $path, int $ttlSeconds): array
    {
        $expiresAt = Carbon::now()->addSeconds($ttlSeconds);

        $url = Storage::disk($disk)->temporaryUrl($path, $expiresAt);

        return [
            'url' => $url,
            'expires_at' => $expiresAt->toIso8601String(),
            'expires_in_seconds' => $ttlSeconds,
        ];
    }
}

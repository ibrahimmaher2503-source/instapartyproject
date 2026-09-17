<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Services;

use App\Modules\Shared\Domain\Contracts\CdnCacheBuster;
use App\Modules\Shared\Domain\Exceptions\CdnCacheBustFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * DigitalOcean Spaces CDN purge implementation.
 *
 * Calls `POST https://api.digitalocean.com/v2/cdn/endpoints/{id}/cache` with
 * `{"files": [paths...]}` and a Bearer token. Synchronous from
 * `DeleteMediaAction` / `ReorderMediaAction`; failure raises
 * `CdnCacheBustFailed` and the HTTP layer maps to 503 retryable.
 *
 * Per ADR-0047 §6 and research.md §3.
 */
final class SpacesCdnCacheBuster implements CdnCacheBuster
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string $endpointId,
        private readonly string $apiToken,
        private readonly int $timeoutSeconds = 10,
    ) {}

    public function purge(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        $url = sprintf('https://api.digitalocean.com/v2/cdn/endpoints/%s/cache', $this->endpointId);

        try {
            $response = $this->http
                ->withToken($this->apiToken)
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->delete($url, ['files' => array_values($paths)]);
        } catch (ConnectionException $e) {
            Log::warning('cdn.bust.connection_failure', ['paths_count' => count($paths), 'error' => $e->getMessage()]);
            throw CdnCacheBustFailed::forPaths($paths, $e);
        } catch (Throwable $e) {
            Log::warning('cdn.bust.unexpected', ['paths_count' => count($paths), 'error' => $e->getMessage()]);
            throw CdnCacheBustFailed::forPaths($paths, $e);
        }

        if (! $response->successful()) {
            Log::warning('cdn.bust.non_2xx', [
                'paths_count' => count($paths),
                'status' => $response->status(),
            ]);
            throw CdnCacheBustFailed::forPaths($paths);
        }
    }
}

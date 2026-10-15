<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

use App\Modules\Settlement\Domain\Exceptions\DuplicateIdempotencyKeyWithDifferentPayloadException;
use App\Modules\Shared\Http\ApiResponse;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IdempotencyService
{
    /**
     * Wrap an operation with idempotency protection for HTTP requests.
     *
     * Returns 422 when the Idempotency-Key header is absent.
     * Returns the cached response when the key was seen within the last 24 h.
     * Stores only 2xx responses — error responses are never cached.
     */
    public function wrap(Request $request, string $route, Closure $callback): JsonResponse
    {
        if (! $request->hasHeader('Idempotency-Key')) {
            return ApiResponse::error('The Idempotency-Key header is required.', 422);
        }

        $key = (string) $request->header('Idempotency-Key');
        $userId = auth()->id();
        $requestHash = hash('sha256', implode('|', [
            $request->getMethod(),
            $request->getRequestUri(),
            $route,
            $request->getContent(),
        ]));
        $ttlSeconds = $this->ttlForScope('http');

        return DB::transaction(function () use ($route, $callback, $key, $userId, $requestHash, $ttlSeconds): JsonResponse {
            DB::table('idempotency_keys')
                ->where('key', $key)
                ->where('scope', 'http')
                ->where('scope_key', 'http')
                ->where('expires_at', '<=', now())
                ->delete();

            $cached = DB::table('idempotency_keys')
                ->where('key', $key)
                ->where('scope', 'http')
                ->where('scope_key', 'http')
                ->lockForUpdate()
                ->first();

            if ($cached !== null) {
                if ((int) $cached->user_id !== (int) $userId
                    || $cached->route !== $route
                    || ! hash_equals((string) $cached->request_hash, $requestHash)) {
                    return ApiResponse::error('Idempotency-Key reused with a different request payload.', 409);
                }

                if ($cached->response_status === null) {
                    return ApiResponse::error('Idempotency request is already in progress.', 409);
                }

                return response()->json(json_decode((string) $cached->response_body, true), (int) $cached->response_status);
            }

            $claimed = DB::table('idempotency_keys')->insertOrIgnore([[
                'key' => $key,
                'scope' => 'http',
                'scope_key' => 'http',
                'ttl_seconds' => $ttlSeconds,
                'user_id' => $userId,
                'route' => $route,
                'request_hash' => $requestHash,
                'payload_hash' => $requestHash,
                'response_status' => null,
                'response_body' => null,
                'expires_at' => now()->addSeconds($ttlSeconds),
                'created_at' => now(),
            ]]);

            if ($claimed === 0) {
                $raced = DB::table('idempotency_keys')
                    ->where('key', $key)
                    ->where('scope', 'http')
                    ->where('scope_key', 'http')
                    ->lockForUpdate()
                    ->first();

                if ($raced === null || (int) $raced->user_id !== (int) $userId
                    || $raced->route !== $route || ! hash_equals((string) $raced->request_hash, $requestHash)
                    || $raced->response_status === null) {
                    return ApiResponse::error('Idempotency-Key conflict or request already in progress.', 409);
                }

                return response()->json(json_decode((string) $raced->response_body, true), (int) $raced->response_status);
            }

            $claimId = DB::table('idempotency_keys')
                ->where('key', $key)
                ->where('scope', 'http')
                ->where('scope_key', 'http')
                ->value('id');
            $response = $callback();

            if ($response->getStatusCode() >= 400) {
                DB::table('idempotency_keys')->where('id', $claimId)->delete();

                return $response;
            }

            $completed = DB::table('idempotency_keys')
                ->where('id', $claimId)
                ->whereNull('response_status')
                ->update([
                    'response_status' => $response->getStatusCode(),
                    'response_body' => $response->getContent(),
                ]);

            return $completed === 1
                ? $response
                : ApiResponse::error('Idempotency request could not be completed.', 409);
        });
    }

    /**
     * Wrap an internal money-mutating operation with idempotency protection.
     *
     * Scope allows distinguishing between HTTP keys (24h), internal webhooks (30d), and
     * internal actions (24h). TTL defaults come from config/idempotency.php.
     *
     * Throws DuplicateIdempotencyKeyWithDifferentPayloadException when the same key is reused
     * with a different payload within the TTL window.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(string $scope, string $key, mixed $payload, Closure $callback): mixed
    {
        $ttlSeconds = $this->ttlForScope($scope);
        $payloadHash = hash('sha256', is_string($payload) ? $payload : json_encode($payload));

        return DB::transaction(function () use ($scope, $key, $payloadHash, $ttlSeconds, $callback): mixed {
            if ($this->hasAmbiguousLegacyKey($scope, $key)) {
                throw new DuplicateIdempotencyKeyWithDifferentPayloadException($key);
            }

            $existing = $this->internalKeyQuery($scope, $key)->where('expires_at', '>', now())->lockForUpdate()->first();

            if ($existing !== null) {
                if ($existing->response_status === null || ! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                    throw new DuplicateIdempotencyKeyWithDifferentPayloadException($key);
                }

                return json_decode((string) ($existing->response_body ?? 'null'), true);
            }

            $this->internalKeyQuery($scope, $key)
                ->where('expires_at', '<=', now())
                ->delete();

            $claimed = DB::table('idempotency_keys')->insertOrIgnore([[
                'key' => $key,
                'user_id' => null,
                'route' => $scope.':'.$key,
                'request_hash' => $payloadHash,
                'response_status' => null,
                'response_body' => null,
                'expires_at' => now()->addSeconds($ttlSeconds),
                'created_at' => now(),
                'scope' => 'internal',
                'scope_key' => $scope,
                'ttl_seconds' => $ttlSeconds,
                'payload_hash' => $payloadHash,
            ]]);

            if ($claimed === 0) {
                $raced = $this->internalKeyQuery($scope, $key)->lockForUpdate()->first();
                if ($raced === null || $raced->response_status === null || ! hash_equals((string) $raced->payload_hash, $payloadHash)) {
                    throw new DuplicateIdempotencyKeyWithDifferentPayloadException($key);
                }

                return json_decode((string) ($raced->response_body ?? 'null'), true);
            }

            $claimId = DB::table('idempotency_keys')
                ->where('key', $key)
                ->where('scope', 'internal')
                ->where('scope_key', $scope)
                ->value('id');
            $result = $callback();
            $completed = DB::table('idempotency_keys')
                ->where('id', $claimId)
                ->whereNull('response_status')
                ->update([
                    'response_status' => 200,
                    'response_body' => json_encode($result),
                ]);

            if ($completed !== 1) {
                throw new DuplicateIdempotencyKeyWithDifferentPayloadException($key);
            }

            return $result;
        });
    }

    /**
     * Check if a key has already been used within its TTL window (read-only).
     */
    public function hasBeenUsed(string $scope, string $key): bool
    {
        if ($this->hasAmbiguousLegacyKey($scope, $key)) {
            throw new DuplicateIdempotencyKeyWithDifferentPayloadException($key);
        }

        $existing = $this->internalKeyQuery($scope, $key)->where('expires_at', '>', now())->first();

        if ($existing === null) {
            return false;
        }

        if ($existing->response_status === null) {
            throw new DuplicateIdempotencyKeyWithDifferentPayloadException($key);
        }

        return true;
    }

    private function internalKeyQuery(string $scope, string $key): Builder
    {
        return DB::table('idempotency_keys')
            ->where('key', $key)
            ->where('route', $scope.':'.$key)
            ->whereIn('scope', [$scope, 'internal', ''])
            ->whereIn('scope_key', [$scope, 'legacy', 'http']);
    }

    private function hasAmbiguousLegacyKey(string $scope, string $key): bool
    {
        return DB::table('idempotency_keys')
            ->where('key', $key)
            ->whereIn('scope', ['internal', ''])
            ->whereIn('scope_key', ['legacy', 'http'])
            ->where('route', '!=', $scope.':'.$key)
            ->where('expires_at', '>', now())
            ->exists();
    }

    private function ttlForScope(string $scope): int
    {
        return match ($scope) {
            'internal_webhook' => (int) config('idempotency.ttl.internal_webhook', 2592000),
            'internal_action' => (int) config('idempotency.ttl.internal_action', 86400),
            default => (int) config('idempotency.ttl.http', 86400),
        };
    }
}

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
use Throwable;

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
        $ttlSeconds = $this->ttlForScope('http');
        $requestHash = hash('sha256', $route.'|'.$request->getContent());

        $cached = DB::table('idempotency_keys')
            ->where('key', $key)
            ->where('user_id', $userId)
            ->where('scope', 'http')
            ->where('expires_at', '>', now())
            ->first();

        if ($cached) {
            if (! hash_equals($cached->request_hash, $requestHash)) {
                return ApiResponse::error('Idempotency-Key reused with a different request payload.', 409);
            }

            if ($cached->response_status === null) {
                return ApiResponse::error('Idempotency request is already in progress.', 409);
            }

            return response()->json(json_decode($cached->response_body, true), $cached->response_status);
        }

        if (DB::table('idempotency_keys')->where('key', $key)->where('expires_at', '>', now())->exists()) {
            return ApiResponse::error('Idempotency-Key is already active for a different user or scope.', 409);
        }

        $this->deleteExpiredKey($key);

        $claimed = DB::table('idempotency_keys')->insertOrIgnore([[
            'key' => $key,
            'user_id' => $userId,
            'route' => $route,
            'request_hash' => $requestHash,
            'response_status' => null,
            'response_body' => null,
            'expires_at' => now()->addSeconds($ttlSeconds),
            'created_at' => now(),
            'scope' => 'http',
            'ttl_seconds' => $ttlSeconds,
            'payload_hash' => $requestHash,
        ]]);

        if ($claimed === 0) {
            $raced = DB::table('idempotency_keys')->where('key', $key)->first();

            if ($raced === null || $raced->scope !== 'http' || (int) $raced->user_id !== (int) $userId
                || $raced->route !== $route || ! hash_equals((string) $raced->request_hash, $requestHash)
                || $raced->response_status === null) {
                return ApiResponse::error('Idempotency key conflict or request already in progress.', 409);
            }

            return response()->json(json_decode($raced->response_body, true), (int) $raced->response_status);
        }

        $claimId = DB::table('idempotency_keys')->where('key', $key)->value('id');

        try {
            $response = $callback();
        } catch (Throwable $exception) {
            DB::table('idempotency_keys')->where('id', $claimId)->delete();
            throw $exception;
        }

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

        if ($completed !== 1) {
            return ApiResponse::error('Idempotency request could not be completed.', 409);
        }

        return $response;
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

        $existing = $this->unexpiredKeyQuery($scope, $key)->first();

        if ($existing !== null) {
            if ($existing->response_status === null) {
                throw new DuplicateIdempotencyKeyWithDifferentPayloadException($key);
            }

            if (! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                throw new DuplicateIdempotencyKeyWithDifferentPayloadException($key);
            }

            $decoded = json_decode((string) ($existing->response_body ?? 'null'), true);

            return $decoded;
        }

        if (DB::table('idempotency_keys')->where('key', $key)->where('expires_at', '>', now())->exists()) {
            throw new DuplicateIdempotencyKeyWithDifferentPayloadException($key);
        }

        $this->deleteExpiredKey($key);

        $claimed = DB::table('idempotency_keys')->insertOrIgnore([[
            'key' => $key,
            'user_id' => null,
            'route' => $scope.':'.$key,
            'request_hash' => $payloadHash,
            'response_status' => null,
            'response_body' => null,
            'expires_at' => now()->addSeconds($ttlSeconds),
            'created_at' => now(),
            'scope' => $scope,
            'ttl_seconds' => $ttlSeconds,
            'payload_hash' => $payloadHash,
        ]]);

        if ($claimed === 0) {
            $raced = $this->unexpiredKeyQuery($scope, $key)->first();

            if ($raced === null || $raced->response_status === null) {
                throw new DuplicateIdempotencyKeyWithDifferentPayloadException($key);
            }

            if (! hash_equals((string) $raced->payload_hash, $payloadHash)) {
                throw new DuplicateIdempotencyKeyWithDifferentPayloadException($key);
            }

            return json_decode((string) ($raced->response_body ?? 'null'), true);
        }

        $claimId = DB::table('idempotency_keys')->where('key', $key)->value('id');

        try {
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
        } catch (Throwable $exception) {
            DB::table('idempotency_keys')
                ->where('id', $claimId)
                ->delete();

            throw $exception;
        }

        return $result;
    }

    /**
     * Check if a key has already been used within its TTL window (read-only).
     */
    public function hasBeenUsed(string $scope, string $key): bool
    {
        $existing = $this->unexpiredKeyQuery($scope, $key)->first();

        if ($existing !== null) {
            if ($existing->response_status === null) {
                throw new DuplicateIdempotencyKeyWithDifferentPayloadException($key);
            }

            return true;
        }

        if (DB::table('idempotency_keys')->where('key', $key)->where('expires_at', '>', now())->exists()) {
            throw new DuplicateIdempotencyKeyWithDifferentPayloadException($key);
        }

        return false;
    }

    private function unexpiredKeyQuery(string $scope, string $key): Builder
    {
        return DB::table('idempotency_keys')
            ->where('key', $key)
            ->whereNull('user_id')
            ->where(function (Builder $query) use ($scope, $key): void {
                $query->where('scope', $scope)
                    ->orWhere(function (Builder $query) use ($scope, $key): void {
                        // Old enum writes coerced unsupported scopes to ''; route kept the exact scope and key.
                        $query->where('scope', '')
                            ->where('route', $scope.':'.$key);
                    });
            })
            ->where('expires_at', '>', now());
    }

    private function deleteExpiredKey(string $key): void
    {
        DB::table('idempotency_keys')
            ->where('key', $key)
            ->where('expires_at', '<=', now())
            ->delete();
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

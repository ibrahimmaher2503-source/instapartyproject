<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Middleware;

use App\Modules\Payments\Domain\Models\IdempotencyKey;
use App\Modules\Shared\Http\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class IdempotencyKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key', '');
        if ($key === '') {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => ['Idempotency-Key header is required']], 422);
        }

        $rawBody = (string) $request->getContent();
        $route = $request->route()?->getName() ?? $request->path();
        $userId = optional($request->user())->id;
        $ttlSeconds = (int) config('idempotency.ttl.http', 86400);
        // Route names omit path parameters, so include the concrete request target
        // and method to prevent one resource's cached response replaying for another.
        $hash = hash('sha256', implode('|', [
            $request->getMethod(),
            $request->getRequestUri(),
            $route,
            $rawBody,
        ]));

        DB::table('idempotency_keys')
            ->where('key', $key)
            ->where('scope', 'http')
            ->where('scope_key', 'http')
            ->where('expires_at', '<=', now())
            ->delete();

        $existing = IdempotencyKey::query()
            ->where('key', $key)
            ->where('scope', 'http')
            ->where('scope_key', 'http')
            ->where('expires_at', '>', now())
            ->first();

        if ($existing !== null) {
            if ((int) $existing->user_id !== (int) $userId || $existing->route !== $route || ! hash_equals($existing->request_hash, $hash)) {
                return ApiResponse::error('Idempotency key conflict', 409);
            }
            if ($existing->response_status === null) {
                return ApiResponse::error('Idempotency request is already in progress', 409);
            }

            $body = $existing->response_body ?? ['data' => null, 'meta' => (object) [], 'errors' => []];

            return response()->json($body, (int) ($existing->response_status ?? 200));
        }

        $claimed = DB::table('idempotency_keys')->insertOrIgnore([
            'key' => $key,
            'scope' => 'http',
            'scope_key' => 'http',
            'ttl_seconds' => $ttlSeconds,
            'user_id' => $userId,
            'route' => $route,
            'request_hash' => $hash,
            'payload_hash' => $hash,
            'response_status' => null,
            'response_body' => null,
            'expires_at' => now()->addSeconds($ttlSeconds),
            'created_at' => now(),
        ]);

        if ($claimed === 0) {
            $existing = IdempotencyKey::query()
                ->where('key', $key)
                ->where('scope', 'http')
                ->where('scope_key', 'http')
                ->first();
            if ($existing === null || $existing->scope !== 'http' || (int) $existing->user_id !== (int) $userId || $existing->route !== $route || ! hash_equals($existing->request_hash, $hash)) {
                return ApiResponse::error('Idempotency key conflict', 409);
            }
            if ($existing->response_status === null) {
                return ApiResponse::error('Idempotency request is already in progress', 409);
            }

            return response()->json($existing->response_body ?? ['data' => null, 'meta' => (object) [], 'errors' => []], (int) $existing->response_status);
        }

        $claimedRow = IdempotencyKey::query()
            ->where('key', $key)
            ->where('scope', 'http')
            ->where('scope_key', 'http')
            ->firstOrFail();
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $claimedRow->delete();
            throw $exception;
        }

        if ($response->getStatusCode() < 400) {
            $body = $response instanceof JsonResponse ? $response->getData(true) : null;
            $claimedRow->update(['response_status' => $response->getStatusCode(), 'response_body' => $body]);
        } else {
            $claimedRow->delete();
        }

        return $response;
    }
}

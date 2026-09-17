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
        $hash = hash('sha256', $route.'|'.$rawBody);

        DB::table('idempotency_keys')
            ->where('key', $key)
            ->where('expires_at', '<=', now())
            ->delete();

        $existing = IdempotencyKey::query()
            ->where('key', $key)
            ->where('scope', 'http')
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
            'ttl_seconds' => 86400,
            'user_id' => $userId,
            'route' => $route,
            'request_hash' => $hash,
            'payload_hash' => $hash,
            'response_status' => null,
            'response_body' => null,
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
        ]);

        if ($claimed === 0) {
            $existing = IdempotencyKey::query()->where('key', $key)->first();
            if ($existing === null || $existing->scope !== 'http' || (int) $existing->user_id !== (int) $userId || $existing->route !== $route || ! hash_equals($existing->request_hash, $hash)) {
                return ApiResponse::error('Idempotency key conflict', 409);
            }
            if ($existing->response_status === null) {
                return ApiResponse::error('Idempotency request is already in progress', 409);
            }

            return response()->json($existing->response_body ?? ['data' => null, 'meta' => (object) [], 'errors' => []], (int) $existing->response_status);
        }

        $claimedRow = IdempotencyKey::query()->where('key', $key)->firstOrFail();
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

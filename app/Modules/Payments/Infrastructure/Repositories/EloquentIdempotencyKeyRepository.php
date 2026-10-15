<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Repositories;

use App\Modules\Payments\Domain\Models\IdempotencyKey;

class EloquentIdempotencyKeyRepository
{
    public function purgeExpired(): int
    {
        return IdempotencyKey::query()
            ->expired()
            ->delete();
    }

    public function lookup(string $key, ?int $userId, string $route): ?IdempotencyKey
    {
        return IdempotencyKey::query()
            ->active()
            ->where('key', $key)
            ->where('scope', 'http')
            ->where('scope_key', 'http')
            ->where('route', $route)
            ->where('user_id', $userId)
            ->first();
    }

    public function put(string $key, ?int $userId, string $route, string $hash, int $status, array $body): void
    {
        IdempotencyKey::query()->updateOrCreate(
            ['key' => $key, 'scope' => 'http', 'scope_key' => 'http'],
            [
                'user_id' => $userId,
                'route' => $route,
                'request_hash' => $hash,
                'payload_hash' => $hash,
                'response_status' => $status,
                'response_body' => $body,
                'ttl_seconds' => (int) config('idempotency.ttl.http', 86400),
                'scope' => 'http',
                'scope_key' => 'http',
                'expires_at' => now()->addHours(24),
                'created_at' => now(),
            ]
        );
    }
}

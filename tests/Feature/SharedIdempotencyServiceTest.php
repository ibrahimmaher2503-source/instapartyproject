<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Http\Middleware\IdempotencyKeyMiddleware;
use App\Modules\Settlement\Domain\Exceptions\DuplicateIdempotencyKeyWithDifferentPayloadException;
use App\Modules\Shared\Application\Services\IdempotencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('stores and replays webhook keys only for the same payload', function (): void {
    $idempotency = app(IdempotencyService::class);
    $key = 'paymob:PURCHASE:order-1:transaction-1:true';
    $payload = ['order_id' => 'order-1', 'transaction_id' => 'transaction-1'];
    $callbackCalls = 0;
    $callback = function () use (&$callbackCalls): array {
        $callbackCalls++;

        return ['captured' => true];
    };

    expect($idempotency->remember('internal_webhook', $key, $payload, $callback))
        ->toBe(['captured' => true]);
    expect(DB::table('idempotency_keys')->where('key', $key)->value('scope'))
        ->toBe('internal');
    expect(DB::table('idempotency_keys')->where('key', $key)->value('scope_key'))
        ->toBe('internal_webhook');
    expect($idempotency->hasBeenUsed('internal_webhook', $key))->toBeTrue();
    expect($idempotency->remember('internal_webhook', $key, $payload, $callback))
        ->toBe(['captured' => true]);
    expect($callbackCalls)->toBe(1);
    expect(fn () => $idempotency->remember(
        'internal_webhook',
        $key,
        ['order_id' => 'order-1', 'transaction_id' => 'different'],
        $callback,
    ))->toThrow(DuplicateIdempotencyKeyWithDifferentPayloadException::class);
});

it('recognizes legacy empty scopes only from their exact internal route marker', function (): void {
    $idempotency = app(IdempotencyService::class);
    $key = 'paymob:legacy:transaction-2:true';
    $payload = 'legacy-payload';
    $payloadHash = hash('sha256', $payload);

    DB::table('idempotency_keys')->insert([
        'key' => $key,
        'scope' => '',
        'user_id' => null,
        'route' => 'internal_webhook:'.$key,
        'request_hash' => $payloadHash,
        'payload_hash' => $payloadHash,
        'response_status' => 200,
        'response_body' => json_encode(['captured' => true]),
        'expires_at' => now()->addDays(30),
        'created_at' => now(),
        'ttl_seconds' => 2_592_000,
    ]);

    $callbackCalls = 0;
    expect($idempotency->hasBeenUsed('internal_webhook', $key))->toBeTrue();
    expect($idempotency->remember('internal_webhook', $key, $payload, function () use (&$callbackCalls): bool {
        $callbackCalls++;

        return false;
    }))->toBe(['captured' => true]);
    expect($callbackCalls)->toBe(0);
    expect(DB::table('idempotency_keys')->where('key', $key)->value('scope'))->toBe('');

    $ambiguousKey = 'paymob:legacy:unknown-scope';
    DB::table('idempotency_keys')->insert([
        'key' => $ambiguousKey,
        'scope' => '',
        'user_id' => null,
        'route' => 'legacy:unknown-scope',
        'request_hash' => $payloadHash,
        'payload_hash' => $payloadHash,
        'response_status' => 200,
        'response_body' => json_encode(['captured' => true]),
        'expires_at' => now()->addDays(30),
        'created_at' => now(),
        'ttl_seconds' => 2_592_000,
    ]);
    expect(fn () => $idempotency->hasBeenUsed('internal_webhook', $ambiguousKey))
        ->toThrow(DuplicateIdempotencyKeyWithDifferentPayloadException::class);
    expect(DB::table('idempotency_keys')->where('key', $ambiguousKey)->value('scope'))->toBe('');
});

it('refreshes an expired webhook key with the configured TTL', function (): void {
    config(['idempotency.ttl.internal_webhook' => 60]);

    $idempotency = app(IdempotencyService::class);
    $key = 'paymob:expired:transaction-3:true';
    $idempotency->remember('internal_webhook', $key, ['revision' => 1], fn () => ['revision' => 1]);
    DB::table('idempotency_keys')->where('key', $key)->update(['expires_at' => now()->subSecond()]);

    $refreshCalls = 0;
    $refresh = function () use (&$refreshCalls): array {
        $refreshCalls++;

        return ['revision' => 2];
    };
    expect($idempotency->remember('internal_webhook', $key, ['revision' => 2], $refresh))
        ->toBe(['revision' => 2]);

    $row = DB::table('idempotency_keys')->where('key', $key)->first();
    expect($idempotency->hasBeenUsed('internal_webhook', $key))->toBeTrue();
    expect((int) $row->ttl_seconds)->toBe(60);
    expect(json_decode((string) $row->response_body, true))->toBe(['revision' => 2]);
    expect($idempotency->remember('internal_webhook', $key, ['revision' => 2], $refresh))
        ->toBe(['revision' => 2]);
    expect($refreshCalls)->toBe(1);
});

it('keeps the same raw key independent between internal and HTTP scopes', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $key = 'cross-scope-key';
    $route = 'admin.bookings.force-cancel';
    $body = '{}';
    $requestHash = hash('sha256', $route.'|'.$body);
    DB::table('idempotency_keys')->insert([
        'key' => $key,
        'scope' => 'internal_action',
        'scope_key' => 'internal_action',
        'user_id' => null,
        'route' => 'internal_action:'.$key,
        'request_hash' => $requestHash,
        'payload_hash' => $requestHash,
        'response_status' => 200,
        'response_body' => json_encode(['source' => 'internal']),
        'expires_at' => now()->addDay(),
        'created_at' => now(),
        'ttl_seconds' => 86_400,
    ]);

    $request = Request::create('/api/test', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => $key], $body);
    $callbackCalls = 0;
    $response = app(IdempotencyService::class)->wrap(
        $request,
        $route,
        function () use (&$callbackCalls) {
            $callbackCalls++;

            return response()->json(['source' => 'http']);
        },
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($callbackCalls)->toBe(1)
        ->and(DB::table('idempotency_keys')->where('key', $key)->where('scope_key', 'internal_action')->exists())->toBeTrue()
        ->and(DB::table('idempotency_keys')->where('key', $key)->where('scope_key', 'http')->value('response_body'))->toBe('{"source":"http"}');
});

it('rejects an active HTTP key owned by another user before the callback', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $key = 'other-user-key';
    $route = 'admin.bookings.force-cancel';
    $body = '{}';
    $requestHash = hash('sha256', $route.'|'.$body);
    DB::table('idempotency_keys')->insert([
        'key' => $key,
        'scope' => 'http',
        'user_id' => $otherUser->id,
        'route' => $route,
        'request_hash' => $requestHash,
        'payload_hash' => $requestHash,
        'response_status' => 200,
        'response_body' => json_encode(['source' => 'other-user']),
        'expires_at' => now()->addDay(),
        'created_at' => now(),
        'ttl_seconds' => 86_400,
    ]);

    $request = Request::create('/api/test', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => $key], $body);
    $callbackCalls = 0;
    $response = app(IdempotencyService::class)->wrap(
        $request,
        $route,
        function () use (&$callbackCalls) {
            $callbackCalls++;

            return response()->json(['source' => 'http']);
        },
    );

    expect($response->getStatusCode())->toBe(409);
    expect($callbackCalls)->toBe(0);
});

it('keeps the same raw key independent between internal action and webhook scopes', function (): void {
    $key = 'cross-internal-scope-key';
    DB::table('idempotency_keys')->insert([
        'key' => $key,
        'scope' => 'internal_action',
        'scope_key' => 'internal_action',
        'user_id' => null,
        'route' => 'internal_action:'.$key,
        'request_hash' => hash('sha256', 'old-payload'),
        'payload_hash' => hash('sha256', 'old-payload'),
        'response_status' => 200,
        'response_body' => json_encode(true),
        'expires_at' => now()->addDay(),
        'created_at' => now(),
        'ttl_seconds' => 86_400,
    ]);

    $callbackCalls = 0;
    expect(app(IdempotencyService::class)->remember('internal_webhook', $key, 'new-payload', function () use (&$callbackCalls): bool {
        $callbackCalls++;

        return true;
    }))->toBeTrue();
    expect($callbackCalls)->toBe(1)
        ->and(DB::table('idempotency_keys')->where('key', $key)->where('scope_key', 'internal_action')->exists())->toBeTrue()
        ->and(DB::table('idempotency_keys')->where('key', $key)->where('scope_key', 'internal_webhook')->exists())->toBeTrue();
});

it('keeps the same raw key independent between HTTP and internal scopes', function (): void {
    $key = 'http-key-cannot-run-internally';
    DB::table('idempotency_keys')->insert([
        'key' => $key,
        'scope' => 'http',
        'scope_key' => 'http',
        'user_id' => User::factory()->create()->id,
        'route' => 'payments.create',
        'request_hash' => hash('sha256', 'existing-http-payload'),
        'payload_hash' => hash('sha256', 'existing-http-payload'),
        'response_status' => 200,
        'response_body' => json_encode(['accepted' => true]),
        'expires_at' => now()->addDay(),
        'created_at' => now(),
        'ttl_seconds' => 86_400,
    ]);
    $callbackCalls = 0;

    expect(app(IdempotencyService::class)->remember('internal_webhook', $key, 'webhook-payload', function () use (&$callbackCalls): bool {
        $callbackCalls++;

        return true;
    }))->toBeTrue();
    expect($callbackCalls)->toBe(1)
        ->and(DB::table('idempotency_keys')->where('key', $key)->where('scope_key', 'http')->value('scope'))->toBe('http')
        ->and(DB::table('idempotency_keys')->where('key', $key)->where('scope_key', 'internal_webhook')->exists())->toBeTrue();
});

it('does not report a key used in another internal scope as used here', function (): void {
    $key = 'checked-cross-internal-scope-key';
    DB::table('idempotency_keys')->insert([
        'key' => $key,
        'scope' => 'internal_action',
        'scope_key' => 'internal_action',
        'user_id' => null,
        'route' => 'internal_action:'.$key,
        'request_hash' => hash('sha256', 'existing-payload'),
        'payload_hash' => hash('sha256', 'existing-payload'),
        'response_status' => 200,
        'response_body' => json_encode(true),
        'expires_at' => now()->addDay(),
        'created_at' => now(),
        'ttl_seconds' => 86_400,
    ]);

    expect(app(IdempotencyService::class)->hasBeenUsed('internal_webhook', $key))->toBeFalse();
});

it('uses the configured HTTP TTL when wrapping a request', function (): void {
    config(['idempotency.ttl.http' => 90]);
    $this->travelTo(now()->startOfSecond());

    $request = Request::create('/api/test', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => 'http-configured-ttl'], '{}');
    $response = app(IdempotencyService::class)->wrap(
        $request,
        'payments.test',
        fn () => response()->json(['ok' => true]),
    );

    $row = DB::table('idempotency_keys')->where('key', 'http-configured-ttl')->first();

    expect($response->getStatusCode())->toBe(200)
        ->and((int) $row->ttl_seconds)->toBe(90)
        ->and(strtotime($row->expires_at) - strtotime($row->created_at))->toBe(90);
});

it('uses the configured HTTP TTL in the idempotency middleware', function (): void {
    config(['idempotency.ttl.http' => 90]);
    $this->travelTo(now()->startOfSecond());

    $user = User::factory()->create();
    Route::post('/api/v1/idempotency-http-ttl', fn () => response()->json(['ok' => true]))
        ->name('payments.idempotency-http-ttl');
    $request = request()->create('/api/v1/idempotency-http-ttl', 'POST', [], [], [], [], '{}');
    $request->setUserResolver(fn () => $user);
    $request->headers->set('Idempotency-Key', 'middleware-configured-ttl');
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);

    $response = app(IdempotencyKeyMiddleware::class)->handle(
        $request,
        fn () => response()->json(['ok' => true]),
    );
    $row = DB::table('idempotency_keys')->where('key', 'middleware-configured-ttl')->first();

    expect($response->getStatusCode())->toBe(200)
        ->and((int) $row->ttl_seconds)->toBe(90)
        ->and(strtotime($row->expires_at) - strtotime($row->created_at))->toBe(90);
});

it('rejects a pending internal claim instead of replaying an empty response', function (): void {
    $key = 'pending-internal-claim';
    $payload = 'pending-payload';
    $hash = hash('sha256', $payload);
    DB::table('idempotency_keys')->insert([
        'key' => $key,
        'scope' => 'internal_webhook',
        'user_id' => null,
        'route' => 'internal_webhook:'.$key,
        'request_hash' => $hash,
        'payload_hash' => $hash,
        'response_status' => null,
        'response_body' => null,
        'expires_at' => now()->addDay(),
        'created_at' => now(),
        'ttl_seconds' => 86_400,
    ]);
    $callbackCalls = 0;

    expect(fn () => app(IdempotencyService::class)->remember('internal_webhook', $key, $payload, function () use (&$callbackCalls): bool {
        $callbackCalls++;

        return true;
    }))->toThrow(DuplicateIdempotencyKeyWithDifferentPayloadException::class);
    expect($callbackCalls)->toBe(0);
});

it('rejects a pending internal claim during the read-only usage check', function (): void {
    $key = 'pending-webhook-claim';
    $hash = hash('sha256', 'pending-payload');
    DB::table('idempotency_keys')->insert([
        'key' => $key,
        'scope' => 'internal_webhook',
        'user_id' => null,
        'route' => 'internal_webhook:'.$key,
        'request_hash' => $hash,
        'payload_hash' => $hash,
        'response_status' => null,
        'response_body' => null,
        'expires_at' => now()->addDay(),
        'created_at' => now(),
        'ttl_seconds' => 86_400,
    ]);

    expect(fn () => app(IdempotencyService::class)->hasBeenUsed('internal_webhook', $key))
        ->toThrow(DuplicateIdempotencyKeyWithDifferentPayloadException::class);
    expect(DB::table('idempotency_keys')->where('key', $key)->value('response_status'))->toBeNull();
});

it('returns conflict for an incomplete HTTP claim without calling the callback', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $key = 'pending-http-claim';
    $route = 'payments.pending-test';
    $body = '{}';
    $requestHash = hash('sha256', $route.'|'.$body);
    DB::table('idempotency_keys')->insert([
        'key' => $key,
        'scope' => 'http',
        'user_id' => $user->id,
        'route' => $route,
        'request_hash' => $requestHash,
        'payload_hash' => $requestHash,
        'response_status' => null,
        'response_body' => null,
        'expires_at' => now()->addDay(),
        'created_at' => now(),
        'ttl_seconds' => 86_400,
    ]);
    $request = Request::create('/api/test', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => $key], $body);
    $callbackCalls = 0;

    $response = app(IdempotencyService::class)->wrap($request, $route, function () use (&$callbackCalls) {
        $callbackCalls++;

        return response()->json(['ok' => true]);
    });

    expect($response->getStatusCode())->toBe(409)
        ->and($callbackCalls)->toBe(0)
        ->and(DB::table('idempotency_keys')->where('key', $key)->value('response_status'))->toBeNull();
});

it('claims an internal key before the callback so overlapping work is rejected', function (): void {
    $idempotency = app(IdempotencyService::class);
    $key = 'overlapping-internal-claim';
    $callbackCalls = 0;
    $overlapException = null;

    $result = $idempotency->remember('internal_webhook', $key, 'same-payload', function () use ($idempotency, $key, &$callbackCalls, &$overlapException): array {
        $callbackCalls++;
        try {
            $idempotency->remember('internal_webhook', $key, 'same-payload', function () use (&$callbackCalls): bool {
                $callbackCalls++;

                return false;
            });
        } catch (DuplicateIdempotencyKeyWithDifferentPayloadException $exception) {
            $overlapException = $exception;
        }

        return ['processed' => true];
    });

    expect($result)->toBe(['processed' => true])
        ->and($callbackCalls)->toBe(1)
        ->and($overlapException)->toBeInstanceOf(DuplicateIdempotencyKeyWithDifferentPayloadException::class)
        ->and(DB::table('idempotency_keys')->where('key', $key)->value('response_status'))->toBe(200);
});

it('claims an HTTP key before the callback so overlapping work receives a conflict', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $idempotency = app(IdempotencyService::class);
    $request = Request::create('/api/test', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => 'overlapping-http-claim'], '{}');
    $route = 'payments.overlapping-test';
    $callbackCalls = 0;
    $nestedResponse = null;

    $response = $idempotency->wrap($request, $route, function () use ($idempotency, $request, $route, &$callbackCalls, &$nestedResponse) {
        $callbackCalls++;
        $nestedResponse = $idempotency->wrap(
            $request,
            $route,
            function () use (&$callbackCalls) {
                $callbackCalls++;

                return response()->json(['nested' => true]);
            },
        );

        return response()->json(['outer' => true]);
    });

    expect($response->getStatusCode())->toBe(200)
        ->and($nestedResponse->getStatusCode())->toBe(409)
        ->and($callbackCalls)->toBe(1)
        ->and(json_decode((string) DB::table('idempotency_keys')->where('key', 'overlapping-http-claim')->value('response_body'), true))
        ->toBe(['outer' => true]);
});

it('releases an internal claim when its callback throws', function (): void {
    $key = 'failed-internal-claim';
    $exception = null;

    try {
        app(IdempotencyService::class)->remember('internal_webhook', $key, 'payload', function (): never {
            throw new RuntimeException('callback failed');
        });
    } catch (RuntimeException $caught) {
        $exception = $caught;
    }

    expect($exception?->getMessage())->toBe('callback failed')
        ->and(DB::table('idempotency_keys')->where('key', $key)->first())->toBeNull();
});

it('releases an HTTP claim when its callback throws', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $key = 'failed-http-claim';
    $request = Request::create('/api/test', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => $key], '{}');

    expect(fn () => app(IdempotencyService::class)->wrap($request, 'payments.failure-test', function (): never {
        throw new RuntimeException('callback failed');
    }))->toThrow(RuntimeException::class, 'callback failed');

    expect(DB::table('idempotency_keys')->where('key', $key)->exists())->toBeFalse();
});

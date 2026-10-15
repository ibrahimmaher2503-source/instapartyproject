<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Domain\Exceptions\DuplicateIdempotencyKeyWithDifferentPayloadException;
use App\Modules\Shared\Application\Services\IdempotencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

it('persists the same raw key independently for internal actions and webhooks', function (): void {
    $service = app(IdempotencyService::class);
    $key = 'qa-verify-shared-internal-key';

    expect($service->remember('internal_action', $key, ['operation' => 'approve'], fn () => ['scope' => 'action']))
        ->toBe(['scope' => 'action'])
        ->and($service->remember('internal_webhook', $key, ['operation' => 'approve'], fn () => ['scope' => 'webhook']))
        ->toBe(['scope' => 'webhook'])
        ->and($service->hasBeenUsed('internal_action', $key))->toBeTrue()
        ->and($service->hasBeenUsed('internal_webhook', $key))->toBeTrue()
        ->and(DB::table('idempotency_keys')->where('key', $key)->where('route', 'internal_action:'.$key)->exists())->toBeTrue()
        ->and(DB::table('idempotency_keys')->where('key', $key)->where('route', 'internal_webhook:'.$key)->exists())->toBeTrue();
});

it('rejects a same-scope internal replay while the first mutation is still running', function (): void {
    $service = app(IdempotencyService::class);
    $key = 'qa-verify-overlapping-internal-key';
    $callbackCalls = 0;
    $overlapException = null;

    $result = $service->remember('internal_webhook', $key, 'same-payload', function () use ($service, $key, &$callbackCalls, &$overlapException): array {
        $callbackCalls++;
        try {
            $service->remember('internal_webhook', $key, 'same-payload', function () use (&$callbackCalls): bool {
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

it('rolls back an internal claim and its database mutation when the callback fails', function (): void {
    $service = app(IdempotencyService::class);
    $key = 'qa-verify-failed-internal-key';
    $usersBefore = User::query()->count();

    expect(fn () => $service->remember('internal_action', $key, 'failing-payload', function (): never {
        User::factory()->create();
        throw new RuntimeException('mutation failed');
    }))->toThrow(RuntimeException::class, 'mutation failed');

    expect(User::query()->count())->toBe($usersBefore)
        ->and(DB::table('idempotency_keys')->where('key', $key)->exists())->toBeFalse();
});

it('releases an expired internal key for a new payload', function (): void {
    config(['idempotency.ttl.internal_webhook' => 90]);
    $service = app(IdempotencyService::class);
    $key = 'qa-verify-expired-internal-key';
    $service->remember('internal_webhook', $key, ['revision' => 1], fn () => ['revision' => 1]);
    DB::table('idempotency_keys')->where('key', $key)->update(['expires_at' => now()->subSecond()]);

    $calls = 0;
    $result = $service->remember('internal_webhook', $key, ['revision' => 2], function () use (&$calls): array {
        $calls++;

        return ['revision' => 2];
    });

    $row = DB::table('idempotency_keys')->where('key', $key)->where('route', 'internal_webhook:'.$key)->first();
    expect($service->hasBeenUsed('internal_webhook', $key))->toBeTrue()
        ->and($result)->toBe(['revision' => 2])
        ->and($calls)->toBe(1)
        ->and((int) $row->ttl_seconds)->toBe(90)
        ->and(json_decode((string) $row->response_body, true))->toBe(['revision' => 2]);
});

it('replays HTTP claims only for the same user and payload and rejects in-flight work', function (): void {
    $service = app(IdempotencyService::class);
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $route = 'qa.verify.idempotency';
    $key = 'qa-verify-http-key';
    $request = Request::create('/api/qa/idempotency', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => $key], '{"value":1}');
    $callbackCalls = 0;
    $nestedResponse = null;
    $this->actingAs($firstUser);

    $first = $service->wrap($request, $route, function () use ($service, $request, $route, &$callbackCalls, &$nestedResponse) {
        $callbackCalls++;
        $nestedResponse = $service->wrap($request, $route, function () use (&$callbackCalls) {
            $callbackCalls++;

            return response()->json(['data' => ['nested' => true]]);
        });

        return response()->json(['data' => ['accepted' => true]], 201);
    });
    $replay = $service->wrap(
        Request::create('/api/qa/idempotency', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => $key], '{"value":1}'),
        $route,
        function () use (&$callbackCalls) {
            $callbackCalls++;

            return response()->json(['data' => ['unexpected' => true]]);
        },
    );
    $mismatch = $service->wrap(
        Request::create('/api/qa/idempotency', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => $key], '{"value":2}'),
        $route,
        function () use (&$callbackCalls) {
            $callbackCalls++;

            return response()->json(['data' => ['unexpected' => true]]);
        },
    );
    $this->actingAs($secondUser);
    $crossUser = $service->wrap(
        Request::create('/api/qa/idempotency', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => $key], '{"value":1}'),
        $route,
        function () use (&$callbackCalls) {
            $callbackCalls++;

            return response()->json(['data' => ['unexpected' => true]]);
        },
    );

    expect($first->getStatusCode())->toBe(201)
        ->and($nestedResponse?->getStatusCode())->toBe(409)
        ->and($replay->getStatusCode())->toBe(201)
        ->and(json_decode($replay->getContent(), true))->toBe(['data' => ['accepted' => true]])
        ->and($mismatch->getStatusCode())->toBe(409)
        ->and($crossUser->getStatusCode())->toBe(409)
        ->and($callbackCalls)->toBe(1);
});

it('does not replay a wrapped HTTP mutation for a different request target', function (): void {
    $service = app(IdempotencyService::class);
    $user = User::factory()->create();
    $route = 'admin.bookings.force-cancel';
    $key = 'qa-verify-http-target-key';
    $body = '{"reason":"duplicate booking"}';
    $callbackCalls = 0;
    $this->actingAs($user);

    $first = $service->wrap(
        Request::create('/admin/bookings/01JBOOKINGONE/force-cancel', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => $key], $body),
        $route,
        function () use (&$callbackCalls) {
            $callbackCalls++;

            return response()->json(['data' => ['booking' => 'one']], 200);
        },
    );
    $differentBooking = $service->wrap(
        Request::create('/admin/bookings/01JBOOKINGTWO/force-cancel', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => $key], $body),
        $route,
        function () use (&$callbackCalls) {
            $callbackCalls++;

            return response()->json(['data' => ['booking' => 'two']], 200);
        },
    );

    expect($first->getStatusCode())->toBe(200)
        ->and($differentBooking->getStatusCode())->toBe(409)
        ->and($callbackCalls)->toBe(1);
});

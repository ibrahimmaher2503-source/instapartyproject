<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Infrastructure\Repositories\EloquentIdempotencyKeyRepository;
use App\Modules\Settlement\Application\Actions\ApproveWithdrawalAction;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('does not replay an unrelated webhook claim as an approved withdrawal', function (): void {
    $withdrawal = Withdrawal::factory()->pending()->create();
    $key = 'wd_approve:'.$withdrawal->id;
    $payloadHash = hash('sha256', 'unrelated-webhook');

    DB::table('idempotency_keys')->insert([
        'key' => $key,
        'user_id' => null,
        'route' => 'internal_webhook:'.$key,
        'request_hash' => $payloadHash,
        'payload_hash' => $payloadHash,
        'response_status' => 200,
        'response_body' => json_encode(['processed' => true], JSON_THROW_ON_ERROR),
        'expires_at' => now()->addDay(),
        'created_at' => now(),
        'scope' => 'internal',
        'scope_key' => 'internal_webhook',
        'ttl_seconds' => 86400,
    ]);

    expect(fn () => app(ApproveWithdrawalAction::class)->execute(
        $withdrawal,
        User::factory()->asAdmin()->create(),
    ))->toThrow(ValidationException::class)
        ->and($withdrawal->fresh()->status->getValue())->toBe('pending')
        ->and(DB::table('idempotency_keys')->where('key', $key)->where('scope_key', 'internal_action')->exists())->toBeFalse()
        ->and(DB::table('idempotency_keys')->where('key', $key)->where('scope_key', 'internal_webhook')->exists())->toBeTrue();
});

it('reads and updates the http key row without overwriting a same-key internal row', function (): void {
    $user = User::factory()->create();
    $key = 'qa-verify-shared-repository-key';
    $route = 'payments.checkout';
    $internalId = DB::table('idempotency_keys')->insertGetId([
        'key' => $key,
        'user_id' => $user->id,
        'route' => $route,
        'request_hash' => hash('sha256', 'internal'),
        'payload_hash' => hash('sha256', 'internal'),
        'response_status' => 200,
        'response_body' => json_encode(['kind' => 'internal'], JSON_THROW_ON_ERROR),
        'expires_at' => now()->addDay(),
        'created_at' => now(),
        'scope' => 'internal',
        'scope_key' => 'booking_vendor_reminder',
        'ttl_seconds' => 86400,
    ]);
    $httpId = DB::table('idempotency_keys')->insertGetId([
        'key' => $key,
        'user_id' => $user->id,
        'route' => $route,
        'request_hash' => hash('sha256', 'http'),
        'payload_hash' => hash('sha256', 'http'),
        'response_status' => 201,
        'response_body' => json_encode(['kind' => 'http'], JSON_THROW_ON_ERROR),
        'expires_at' => now()->addDay(),
        'created_at' => now(),
        'scope' => 'http',
        'scope_key' => 'http',
        'ttl_seconds' => 86400,
    ]);

    $repository = new EloquentIdempotencyKeyRepository;
    $found = $repository->lookup($key, $user->id, $route);
    $repository->put($key, $user->id, $route, hash('sha256', 'updated'), 202, ['kind' => 'updated']);

    expect($found?->id)->toBe($httpId)
        ->and(DB::table('idempotency_keys')->where('id', $httpId)->value('response_status'))->toBe(202)
        ->and(json_decode((string) DB::table('idempotency_keys')->where('id', $httpId)->value('response_body'), true))->toBe(['kind' => 'updated'])
        ->and(DB::table('idempotency_keys')->where('id', $internalId)->value('response_status'))->toBe(200)
        ->and(json_decode((string) DB::table('idempotency_keys')->where('id', $internalId)->value('response_body'), true))->toBe(['kind' => 'internal']);
});

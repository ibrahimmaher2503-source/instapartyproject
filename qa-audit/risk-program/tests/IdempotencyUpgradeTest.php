<?php

declare(strict_types=1);

use App\Modules\Shared\Application\Services\IdempotencyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/FinancialPilotTest.php';

/** Existing-schema upgrade proof; run only through the guarded risk-program MySQL fixture. */
final class IdempotencyUpgradeTest extends FinancialPilotTest
{
    public function test_independent_upgrade_rq_a_001_existing_coerced_scope_remains_replayable(): void
    {
        DB::table('idempotency_keys')->delete();

        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->enum('scope', ['http', 'internal'])->default('http')->change();
        });

        $key = 'paymob:legacy-upgrade:transaction:true';
        $payload = 'existing-webhook-payload';
        $payloadHash = hash('sha256', $payload);
        DB::table('idempotency_keys')->insertOrIgnore([
            'key' => $key,
            'scope' => 'internal_webhook',
            'user_id' => null,
            'route' => 'internal_webhook:'.$key,
            'request_hash' => $payloadHash,
            'payload_hash' => $payloadHash,
            'response_status' => 200,
            'response_body' => json_encode(['accepted' => true]),
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
            'ttl_seconds' => 2_592_000,
        ]);

        $before = (array) DB::table('idempotency_keys')->where('key', $key)->first();
        self::assertSame('', $before['scope'], 'The old enum should retain MariaDB’s coerced empty scope.');

        $migration = require base_path('app/Modules/Payments/Database/Migrations/2026_10_12_000001_store_idempotency_scopes_as_strings.php');
        $migration->up();

        $after = (array) DB::table('idempotency_keys')->where('key', $key)->first();
        self::assertSame($before, $after, 'The forward migration must not rewrite ambiguous legacy data.');

        $callbackCalls = 0;
        $idempotency = app(IdempotencyService::class);
        self::assertTrue($idempotency->hasBeenUsed('internal_webhook', $key));
        self::assertSame(
            ['accepted' => true],
            $idempotency->remember('internal_webhook', $key, $payload, function () use (&$callbackCalls): bool {
                $callbackCalls++;

                return false;
            }),
        );
        self::assertSame(0, $callbackCalls);

        $rollbackFailure = null;
        try {
            $migration->down();
        } catch (RuntimeException $exception) {
            $rollbackFailure = $exception;
        }

        self::assertInstanceOf(RuntimeException::class, $rollbackFailure);
        self::assertSame($after, (array) DB::table('idempotency_keys')->where('key', $key)->first());
    }
}

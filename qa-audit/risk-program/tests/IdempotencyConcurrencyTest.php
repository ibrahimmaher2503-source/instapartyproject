<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Application\Services\IdempotencyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

require_once __DIR__.'/FinancialPilotTest.php';

final class IdempotencyConcurrencyTest extends FinancialPilotTest
{
    public function test_independent_concurrency_only_one_http_or_internal_callback_executes(): void
    {
        Schema::create('qa_idempotency_callbacks', function (Blueprint $table): void {
            $table->id();
            $table->string('operation');
        });
        $customer = User::factory()->asCustomer()->create();
        $results = [];

        foreach (['internal', 'http'] as $mode) {
            $processes = [];
            foreach ([0, 1] as $index) {
                $process = new Process([
                    PHP_BINARY, base_path('qa-audit/risk-program/tests/idempotency-worker.php'),
                    $mode, (string) $customer->id, (string) $index,
                ]);
                $process->setTimeout(60);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 30;
            while ((! file_exists(getenv('QA_OUTPUT').'/'.$mode.'-0.ready')
                || ! file_exists(getenv('QA_OUTPUT').'/'.$mode.'-1.ready')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            self::assertFileExists(getenv('QA_OUTPUT').'/'.$mode.'-0.ready');
            self::assertFileExists(getenv('QA_OUTPUT').'/'.$mode.'-1.ready');
            touch(getenv('QA_OUTPUT').'/'.$mode.'.go');

            $statuses = [];
            foreach ($processes as $process) {
                $process->wait();
                self::assertTrue($process->isSuccessful(), 'The guarded callback worker must finish.');
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                self::assertContains($result['status'], [200, 409]);
                $statuses[] = $result['status'];
            }
            self::assertContains(200, $statuses);
            $effects = DB::table('qa_idempotency_callbacks')->where('operation', $mode)->count();
            self::assertSame(1, $effects, 'A replay or in-progress conflict must not execute another callback.');
            $row = DB::table('idempotency_keys')->where('key', 'qa-concurrent-'.$mode)->first();
            self::assertNotNull($row);
            self::assertSame(200, (int) $row->response_status);
            self::assertSame(['done' => true], json_decode($row->response_body, true));
            $results[$mode] = ['statuses' => $statuses, 'callback_effects' => $effects];
        }

        self::assertTrue(app(IdempotencyService::class)->hasBeenUsed('internal_action', 'qa-concurrent-internal'));
        file_put_contents(getenv('QA_OUTPUT').'/idempotency-concurrency.json', json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}

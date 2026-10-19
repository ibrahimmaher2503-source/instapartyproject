<?php

declare(strict_types=1);
use App\Modules\Settlement\Domain\Exceptions\DuplicateIdempotencyKeyWithDifferentPayloadException;
use App\Modules\Shared\Application\Services\IdempotencyService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing')
    || ! str_starts_with((string) config('database.connections.mysql.database'), 'instaparty_qa_')
    || ! str_starts_with((string) config('database.connections.mysql.unix_socket'), '/tmp/iqa-mysql-')) {
    throw new RuntimeException('Disposable callback fixture only');
}
$mode = $argv[1];
touch(getenv('QA_OUTPUT').'/'.$mode.'-'.$argv[3].'.ready');
$deadline = microtime(true) + 30;
while (! file_exists(getenv('QA_OUTPUT').'/'.$mode.'.go') && microtime(true) < $deadline) {
    usleep(10000);
}

try {
    $service = app(IdempotencyService::class);
    $callback = function () use ($mode): array {
        DB::table('qa_idempotency_callbacks')->insert(['operation' => $mode]);
        // Hold the callback open so the other process observes a claimed, incomplete key.
        usleep(500000);

        return ['done' => true];
    };
    if ($mode === 'internal') {
        $service->remember('internal_action', 'qa-concurrent-internal', 'fixture', $callback);
        $status = 200;
    } else {
        Auth::loginUsingId((int) $argv[2]);
        $request = Request::create('/qa-callback', 'POST', content: '{}');
        $request->headers->set('Idempotency-Key', 'qa-concurrent-http');
        $status = $service->wrap($request, 'qa-callback', fn () => response()->json($callback()))->getStatusCode();
    }
} catch (DuplicateIdempotencyKeyWithDifferentPayloadException) {
    $status = 409;
} catch (Throwable) {
    $status = 500;
}
echo json_encode(['status' => $status], JSON_THROW_ON_ERROR);

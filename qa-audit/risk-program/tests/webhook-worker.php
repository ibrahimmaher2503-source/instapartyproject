<?php

declare(strict_types=1);
use App\Modules\Payments\Application\DTOs\PaymobWebhookDto;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Events\PaymentFailed;
use App\Modules\Payments\Domain\Models\Payment;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['app.debug' => true]);
if (! app()->environment('testing')
    || ! str_starts_with((string) config('database.connections.mysql.database'), 'instaparty_qa_')
    || ! str_starts_with((string) config('database.connections.mysql.unix_socket'), '/tmp/iqa-mysql-')) {
    throw new RuntimeException('Disposable webhook fixture only');
}
Http::preventStrayRequests();
$gateway = Mockery::mock(PaymentGateway::class);
$gateway->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
$gateway->shouldReceive('parseWebhook')->once()->andReturn(new PaymobWebhookDto(
    gatewayRef: $argv[1], success: false, failureCode: 'declined', capturedAmount: null, rawPayload: [],
));
$app->instance(PaymentGateway::class, $gateway);
Payment::updating(function (): void {
    // Force overlapping handlers before their first payment write; all writes/listeners remain real.
    usleep(500000);
});
Event::listen(PaymentFailed::class, function ($event): void {
    DB::table('qa_payment_failed_events')->insert(['payment_id' => $event->paymentId]);
});
touch(getenv('QA_OUTPUT').'/webhook-'.$argv[2].'.ready');
$deadline = microtime(true) + 30;
while (! file_exists(getenv('QA_OUTPUT').'/webhook.go') && microtime(true) < $deadline) {
    usleep(10000);
}
$payload = ['type' => 'transaction_processed', 'obj' => ['id' => $argv[1], 'success' => false]];
$request = Request::create('/api/v1/webhooks/paymob', 'POST', server: [
    'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_HMAC' => 'local-fixture-only',
], content: json_encode($payload, JSON_THROW_ON_ERROR));
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
$kernel->terminate($request, $response);
$result = ['status' => $response->getStatusCode()];
if ($response->getStatusCode() >= 500) {
    $error = json_decode($response->getContent(), true);
    $result['exception'] = $error['exception'] ?? null;
    $result['message'] = $error['message'] ?? null;
}
file_put_contents(getenv('QA_OUTPUT').'/webhook-'.$argv[2].'.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_THROW_ON_ERROR);

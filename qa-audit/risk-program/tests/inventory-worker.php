<?php

declare(strict_types=1);
use App\Modules\Booking\Application\Actions\AddItemToBookingAction;
use App\Modules\Booking\Application\Actions\CustomerConfirmModifiedBookingAction;
use App\Modules\Booking\Application\DTOs\AddBookingItemDTO;
use App\Modules\Booking\Application\DTOs\CustomerModificationDecisionDTO;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! str_starts_with((string) config('database.connections.mysql.database'), 'instaparty_qa_')) {
    throw new RuntimeException('Disposable fixture only');
}
$mode = $argv[5] ?? 'add-item';
$gate = $argv[6] ?? 'inventory';
$readyFile = $gate === 'inventory' ? 'worker-'.$argv[4].'.ready' : $gate.'-'.$argv[4].'.ready';
touch(getenv('QA_OUTPUT').'/'.$readyFile);
$deadline = microtime(true) + 30;
while (! file_exists(getenv('QA_OUTPUT').'/'.$gate.'.go') && microtime(true) < $deadline) {
    usleep(10000);
}
try {
    if ($mode === 'confirm-modification') {
        Event::fake();
        app(CustomerConfirmModifiedBookingAction::class)->execute(new CustomerModificationDecisionDTO((int) $argv[1], (int) $argv[2], (string) $argv[7], 'accepted', 'qa-inventory-'.$argv[4]));
        $status = 200;
    } else {
        $startsAt = isset($argv[7]) && $argv[7] !== '' ? Carbon::parse($argv[7]) : null;
        $endsAt = isset($argv[8]) && $argv[8] !== '' ? Carbon::parse($argv[8]) : null;
        app(AddItemToBookingAction::class)->execute(new AddBookingItemDTO((int) $argv[1], (int) $argv[2], (int) $argv[3], 1, $startsAt, $endsAt, null));
        $status = 201;
    }
} catch (HttpResponseException $error) {
    $status = $error->getResponse()->getStatusCode();
} catch (HttpException $error) {
    $status = $error->getStatusCode();
}
echo json_encode(['status' => $status], JSON_THROW_ON_ERROR);

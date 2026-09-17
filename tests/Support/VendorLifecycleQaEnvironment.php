<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Fakes\FakeOtpGateway;
use Tests\Fakes\FakePaymentGateway;

final class VendorLifecycleQaEnvironment
{
    public static function boot(): void
    {
        self::assertIsolated();

        app()->singleton(PaymentGateway::class, FakePaymentGateway::class);
        app()->singleton(OtpGatewayInterface::class, FakeOtpGateway::class);

        Mail::fake();
        Notification::fake();
        Queue::fake();
        Storage::fake((string) config('filesystems.default', 'local'));
        Carbon::setTestNow('2026-09-06 10:00:00 UTC');
    }

    public static function assertIsolated(): void
    {
        $database = (string) config('database.connections.sqlite.database');
        $qaFile = str_starts_with($database, '/tmp/instaparty-qa-') && str_ends_with($database, '.sqlite');

        $safe = app()->environment('testing')
            && config('database.default') === 'sqlite'
            && ($database === ':memory:' || $qaFile)
            && config('mail.default') === 'array'
            && config('queue.default') === 'sync'
            && config('services.otp.send_on_registration') === false;

        if (! $safe) {
            throw new RuntimeException('Vendor lifecycle QA refused: testing/SQLite/fake-delivery isolation is not active.');
        }
    }

    public static function resetClock(): void
    {
        Carbon::setTestNow();
    }
}

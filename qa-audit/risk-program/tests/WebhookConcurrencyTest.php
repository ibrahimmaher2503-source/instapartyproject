<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Communication\Database\Factories\NotificationTemplateFactory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Models\Payment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

require_once __DIR__.'/FinancialPilotTest.php';

final class WebhookConcurrencyTest extends FinancialPilotTest
{
    public function test_independent_failure_callbacks_emit_one_event_and_one_notification_set(): void
    {
        Schema::create('qa_payment_failed_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payment_id');
        });
        $customer = User::factory()->phoneVerified()->asCustomer()->create();
        $booking = Booking::factory()->create([
            'customer_id' => $customer->id, 'lifecycle_status' => 'confirmed',
            'payment_status' => 'unpaid', 'total_minor' => 10000, 'subtotal_minor' => 10000,
        ]);
        $payment = Payment::factory()->create([
            'booking_id' => $booking->id, 'user_id' => $customer->id,
            'amount_minor' => 10000, 'amount_currency' => 'EGP',
        ]);
        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            NotificationTemplateFactory::new()->create([
                'event_key' => 'payment.failed.customer', 'channel' => $channel,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => 'Payment failed', 'ar' => 'فشل الدفع'],
                'body' => ['en' => 'Payment failed.', 'ar' => 'فشل الدفع.'], 'variables' => [],
            ]);
        }
        $processes = [];
        foreach ([0, 1] as $index) {
            $process = new Process([
                PHP_BINARY, base_path('qa-audit/risk-program/tests/webhook-worker.php'),
                (string) $payment->gateway_ref, (string) $index,
            ]);
            $process->setTimeout(60);
            $process->start();
            $processes[] = $process;
        }
        $deadline = microtime(true) + 30;
        while ((! file_exists(getenv('QA_OUTPUT').'/webhook-0.ready')
            || ! file_exists(getenv('QA_OUTPUT').'/webhook-1.ready')) && microtime(true) < $deadline) {
            usleep(10000);
        }
        self::assertFileExists(getenv('QA_OUTPUT').'/webhook-0.ready');
        self::assertFileExists(getenv('QA_OUTPUT').'/webhook-1.ready');
        touch(getenv('QA_OUTPUT').'/webhook.go');
        $statuses = [];
        foreach ($processes as $process) {
            $process->wait();
            self::assertTrue($process->isSuccessful());
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertContains($result['status'], [200, 409]);
            $statuses[] = $result['status'];
        }
        $events = DB::table('qa_payment_failed_events')->count();
        $channels = DB::table('notification_dispatches')
            ->whereIn('notification_template_id', DB::table('notification_templates')
                ->select('id')->where('event_key', 'payment.failed.customer'))
            ->where('user_id', $customer->id)->orderBy('channel')->pluck('channel')->all();
        $notifications = count($channels);
        file_put_contents(getenv('QA_OUTPUT').'/webhook-concurrency.json', json_encode([
            'statuses' => $statuses, 'failure_events' => $events, 'customer_notifications' => $notifications,
            'notification_channels' => $channels,
            'payment_status' => $payment->fresh()->status->getValue(),
            'ledger_entries' => DB::table('wallet_ledger')->count(),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        self::assertContains(200, $statuses);
        self::assertSame(1, $events, 'An identical concurrent failed callback must emit one failure event.');
        self::assertSame(2, $notifications, 'The customer receives one push/email notification set.');
        self::assertSame(['email', 'push'], $channels);
        self::assertSame('failed', $payment->fresh()->status->getValue());
        self::assertSame(0, DB::table('wallet_ledger')->count());
    }
}

<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ConfirmedState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use App\Modules\Payments\Application\DTOs\PaymobWebhookDto;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Domain\Models\Payment;
use Brick\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

final class WebhookMismatchTest extends \Tests\TestCase
{
    use RefreshDatabase;

    public function test_B04_VTSE_007_mismatched_capture_preserves_payment_booking_and_ledger(): void
    {
        self::assertSame('testing', app()->environment());
        self::assertSame(':memory:', config('database.connections.sqlite.database'));
        $results = [];
        foreach ([[9999, 'EGP'], [10000, 'USD']] as [$minor, $currency]) {
            $booking = Booking::factory()->create(['lifecycle_status' => ConfirmedState::class, 'payment_status' => UnpaidState::class, 'total_minor' => 10000, 'total_currency' => 'EGP']);
            $payment = Payment::factory()->create(['booking_id' => $booking->id, 'amount_minor' => 10000, 'amount_currency' => 'EGP']);
            $before = DB::table('wallet_ledger')->count();
            $gateway = Mockery::mock(PaymentGateway::class);
            $gateway->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
            $gateway->shouldReceive('parseWebhook')->once()->andReturn(new PaymobWebhookDto(
                gatewayRef: $payment->gateway_ref, success: true, failureCode: null,
                capturedAmount: Money::ofMinor($minor, $currency), rawPayload: [],
            ));
            $this->app->instance(PaymentGateway::class, $gateway);
            // Only gateway authentication/parsing is substituted; HTTP, capture and ledger bindings are real.
            $response = $this->postJson('/api/v1/webhooks/paymob', ['type' => 'transaction_processed', 'obj' => ['id' => $payment->gateway_ref, 'success' => true]], ['HMAC' => 'benchmark-local-only']);
            $payment->refresh();
            $booking->refresh();
            $log = GatewayWebhookLog::query()->latest('id')->firstOrFail();
            $results[] = ['received_minor' => $minor, 'received_currency' => $currency, 'http_status' => $response->status(), 'payment_status' => $payment->status->getValue(), 'booking_payment_status' => $booking->payment_status->getValue(), 'ledger_delta' => DB::table('wallet_ledger')->count() - $before, 'processing_error' => $log->processing_error];
            file_put_contents(base_path('.e2e/system-audit/benchmark/B04.json'), json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $response->assertOk();
            self::assertSame('pending', $payment->status->getValue());
            self::assertNull($payment->captured_at);
            self::assertNull($payment->capture_ledger_group_id);
            self::assertSame('unpaid', $booking->payment_status->getValue());
            self::assertSame($before, DB::table('wallet_ledger')->count());
            self::assertSame('captured_amount_or_currency_mismatch', $log->processing_error);
        }
    }
}

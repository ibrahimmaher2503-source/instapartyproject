<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Contracts\CatalogServiceReader;
use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingModificationItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Application\Actions\ProcessRefundAction;
use App\Modules\Payments\Application\DTOs\PaymobWebhookDto;
use App\Modules\Payments\Application\DTOs\RefundResultDto;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Events\LedgerTransactionPosted;
use App\Modules\Settlement\Domain\Exceptions\DuplicateIdempotencyKeyWithDifferentPayloadException;
use App\Modules\Settlement\Domain\Exceptions\UnbalancedTransactionException;
use App\Modules\Settlement\Domain\Exceptions\WalletCurrencyMismatchException;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Shared\Application\Services\IdempotencyService;
use Brick\Money\Money;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FinancialPilotTest extends TestCase
{
    use DatabaseTruncation;

    private array $evidence = [];

    private array $events = [];

    public function createApplication()
    {
        // Guard before the framework migrates/truncates this disposable database.
        if (getenv('APP_ENV') !== 'testing' || ! str_starts_with((string) getenv('DB_DATABASE'), 'instaparty_qa_')
            || ! str_starts_with((string) getenv('DB_SOCKET'), '/tmp/iqa-mysql-') || ! getenv('QA_OUTPUT')) {
            throw new RuntimeException('Only the private socket-only disposable MySQL instance is permitted.');
        }

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('mysql', config('database.default'));
        self::assertSame(getenv('DB_DATABASE'), DB::selectOne('SELECT DATABASE() AS name')->name);
        Http::preventStrayRequests();
        foreach ([PaymentCaptured::class, RefundCompleted::class, LedgerTransactionPosted::class] as $event) {
            Event::listen($event, function ($value) use ($event): void {
                $this->events[] = $event;
            });
        }
    }

    private function check(string $scenario, string $assertion, mixed $expected, mixed $actual): void
    {
        $this->evidence[$scenario]['assertions'][] = compact('assertion', 'expected', 'actual');
        $this->evidence[$scenario]['events'] = $this->events;
        $this->evidence[$scenario]['persistence'] = [
            'payments' => DB::table('payments')->get(['public_id', 'status', 'amount_minor', 'amount_currency', 'capture_ledger_group_id'])->toArray(),
            'refunds' => DB::table('refunds')->get(['public_id', 'status', 'amount_minor', 'amount_currency'])->toArray(),
            'ledger' => DB::table('wallet_ledger')->get(['id', 'transaction_group_id', 'direction', 'amount_minor', 'currency'])->toArray(),
            'wallets' => DB::table('wallets')->get(['id', 'currency', 'balance_minor', 'pending_withdrawal_minor'])->toArray(),
            'webhook_errors' => DB::table('gateway_webhook_logs')->pluck('processing_error')->toArray(),
            'idempotency_count' => DB::table('idempotency_keys')->count(),
            'idempotency_rows' => DB::table('idempotency_keys')->get(['key', 'scope', 'ttl_seconds', 'created_at', 'expires_at'])->toArray(),
            'inventory' => DB::table('service_inventory_reservations')->get(['service_id', 'quantity', 'status', 'expires_at'])->toArray(),
            'sale_stock' => DB::table('service_sale_details')->get(['service_id', 'stock_quantity'])->toArray(),
            'database_now' => DB::selectOne('SELECT NOW() AS value')->value,
            'application_now' => now()->toDateTimeString(),
            'isolation' => DB::selectOne('SELECT @@transaction_isolation AS value')->value,
            'database_engine' => DB::selectOne('SELECT VERSION() AS version')->version,
        ];
        file_put_contents(getenv('QA_OUTPUT').'/'.$scenario.'.json', json_encode($this->evidence[$scenario], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        self::assertSame($expected, $actual, $assertion);
    }

    private function pending(): Payment
    {
        $customer = User::factory()->phoneVerified()->asCustomer()->create();
        $booking = Booking::factory()->create(['customer_id' => $customer->id, 'lifecycle_status' => 'confirmed', 'payment_status' => 'unpaid', 'total_minor' => 10000, 'subtotal_minor' => 10000, 'total_vat_minor' => 0]);

        return Payment::factory()->create(['booking_id' => $booking->id, 'user_id' => $customer->id, 'amount_minor' => 10000, 'amount_currency' => 'EGP']);
    }

    private function gatewayCallback(Payment $payment, ?Money $amount, bool $valid = true): TestResponse
    {
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('verifyWebhookSignature')->once()->andReturn($valid);
        if ($valid) {
            $gateway->shouldReceive('parseWebhook')->once()->andReturn(new PaymobWebhookDto($payment->gateway_ref, true, null, $amount, []));
        }
        $this->app->instance(PaymentGateway::class, $gateway);

        return $this->postJson('/api/v1/webhooks/paymob', ['type' => 'transaction_processed', 'obj' => ['id' => $payment->gateway_ref, 'order' => ['id' => $payment->booking_id], 'success' => true]], ['HMAC' => 'isolated-gateway-boundary']);
    }

    public function test_s_c_payments_3436fcba08_mismatch_amount_currency_and_missing_amount(): void
    {
        $id = 'SC-Payments-3436fcba08';
        foreach ([Money::ofMinor(9999, 'EGP'), Money::ofMinor(10000, 'USD'), null] as $amount) {
            $payment = $this->pending();
            $before = DB::table('wallet_ledger')->count();
            $result = $this->gatewayCallback($payment, $amount);
            $this->check($id, 'callback acknowledged after reaching real guard', 200, $result->status());
            $this->check($id, 'mismatch log', 'captured_amount_or_currency_mismatch', GatewayWebhookLog::latest('id')->first()->processing_error);
            $this->check($id, 'payment pending', 'pending', $payment->fresh()->status->getValue());
            $this->check($id, 'no capture timestamp', null, $payment->fresh()->captured_at);
            $this->check($id, 'no capture group', null, $payment->fresh()->capture_ledger_group_id);
            $this->check($id, 'booking unpaid', 'unpaid', DB::table('bookings')->where('id', $payment->booking_id)->value('payment_status'));
            $this->check($id, 'no ledger mutation', $before, DB::table('wallet_ledger')->count());
            $this->check($id, 'no captured event', 0, count(array_filter($this->events, fn ($e) => $e === PaymentCaptured::class)));
        }
    }

    public function test_s_c_payments_db31a76816_capture_commits_booking_ledger_and_idempotency(): void
    {
        $id = 'SC-Payments-db31a76816';
        $payment = $this->pending();
        $result = $this->gatewayCallback($payment, Money::ofMinor(10000, 'EGP'));
        $this->check($id, 'callback status', 200, $result->status());
        $payment->refresh();
        $this->check($id, 'captured', 'captured', $payment->status->getValue());
        $this->check($id, 'capture timestamp present', true, $payment->captured_at !== null);
        $this->check($id, 'capture group linked', true, $payment->capture_ledger_group_id !== null);
        $this->check($id, 'booking paid', 'paid', DB::table('bookings')->where('id', $payment->booking_id)->value('payment_status'));
        $this->check($id, 'exact booking amount', 10000, (int) DB::table('bookings')->where('id', $payment->booking_id)->value('amount_paid_minor'));
        $this->check($id, 'group balanced', 0, $this->imbalance());
        $this->check($id, 'exact capture debit', 10000, (int) DB::table('wallet_ledger')->where('transaction_group_id', $payment->capture_ledger_group_id)->where('direction', 'debit')->sum('amount_minor'));
        $this->check($id, 'real idempotency recorded', true, DB::table('idempotency_keys')->exists());
        $this->check($id, 'after commit captured event', 1, count(array_filter($this->events, fn ($e) => $e === PaymentCaptured::class)));
    }

    public function test_s_c_payments_db566d3009_duplicate_callback_has_no_financial_or_event_effect(): void
    {
        $id = 'SC-Payments-db566d3009';
        $payment = $this->pending();
        $this->gatewayCallback($payment, Money::ofMinor(10000, 'EGP'))->assertOk();
        $before = [DB::table('wallet_ledger')->count(), $payment->fresh()->capture_ledger_group_id, $this->events];
        $response = $this->gatewayCallback($payment, Money::ofMinor(10000, 'EGP'));
        $this->check($id, 'replay status', 200, $response->status());
        $this->check($id, 'no duplicate financial/event effects', $before, [DB::table('wallet_ledger')->count(), $payment->fresh()->capture_ledger_group_id, $this->events]);
        $this->check($id, 'webhook key retrievable in its real scope', true, app(IdempotencyService::class)->hasBeenUsed('internal_webhook', 'paymob:PURCHASE:'.$payment->booking_id.':'.$payment->gateway_ref.':true'));
        $this->check($id, 'duplicate log', 'duplicate_idempotent_replay', GatewayWebhookLog::latest('id')->first()->processing_error);
    }

    public function test_s_c_payments_199cb154e1_invalid_signature_rejected_before_capture(): void
    {
        $id = 'SC-Payments-199cb154e1';
        $payment = $this->pending();
        $response = $this->gatewayCallback($payment, null, false);
        $this->check($id, 'unauthorized callback', 401, $response->status());
        $this->check($id, 'invalid signature logged', 'invalid_signature', GatewayWebhookLog::latest('id')->first()->processing_error);
        $this->check($id, 'payment unchanged', 'pending', $payment->fresh()->status->getValue());
        $this->check($id, 'no ledger', 0, DB::table('wallet_ledger')->count());
        $this->check($id, 'no idempotency consumption', 0, DB::table('idempotency_keys')->count());
        $this->check($id, 'no financial events', [], $this->events);
    }

    public function test_s_c_payments_83d4a64c4f_other_customer_payment_not_disclosed(): void
    {
        $id = 'SC-Payments-83d4a64c4f';
        $payment = $this->pending();
        $other = User::factory()->phoneVerified()->asCustomer()->create();
        Sanctum::actingAs($other);
        $response = $this->getJson('/api/v1/customer/payments/'.$payment->public_id);
        $this->check($id, 'foreign payment 404', 404, $response->status());
        $this->check($id, 'no gateway or amount disclosed', false, str_contains($response->getContent(), $payment->gateway_ref) || str_contains($response->getContent(), 'amount_minor'));
        $this->check($id, 'payment unchanged', 'pending', $payment->fresh()->status->getValue());
        Sanctum::actingAs(User::findOrFail($payment->user_id));
        $control = $this->getJson('/api/v1/customer/payments/'.$payment->public_id);
        $this->check($id, 'owner control 200', 200, $control->status());
        $this->check($id, 'public booking ID only', false, array_key_exists('booking_id', $control->json('data')));
    }

    private function refundPayment(): array
    {
        $payment = $this->pending();
        $this->gatewayCallback($payment, Money::ofMinor(10000, 'EGP'))->assertOk();
        $refund = Refund::factory()->create(['payment_id' => $payment->id, 'booking_id' => $payment->booking_id, 'amount_minor' => 10000, 'amount_currency' => 'EGP']);
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('refund')->once()->andReturn(new RefundResultDto(true, 'fake-refund-'.$refund->public_id, null));
        $this->app->instance(PaymentGateway::class, $gateway);
        app(ProcessRefundAction::class)->execute($refund->id);

        return [$payment->fresh(), $refund->fresh()];
    }

    public function test_s_c_payments_e7d1c7c10f_full_refund_commits_reversal_and_booking_projection(): void
    {
        $id = 'SC-Payments-e7d1c7c10f';
        [$payment, $refund] = $this->refundPayment();
        $this->check($id, 'refund completed', 'completed', $refund->status->value);
        $this->check($id, 'payment refunded', 'refunded', $payment->status->getValue());
        $this->check($id, 'booking amount returned to zero', 0, (int) DB::table('bookings')->where('id', $payment->booking_id)->value('amount_paid_minor'));
        $this->check($id, 'booking refunded', 'refunded', DB::table('bookings')->where('id', $payment->booking_id)->value('payment_status'));
        $this->check($id, 'all groups balanced', 0, $this->imbalance());
        $this->check($id, 'refund event after commit', 1, count(array_filter($this->events, fn ($e) => $e === RefundCompleted::class)));
        $this->check($id, 'full refund ledger debit', 10000, (int) DB::table('wallet_ledger')->where('related_entity_type', 'refund')->where('related_entity_id', $refund->id)->where('direction', 'debit')->sum('amount_minor'));
    }

    public function test_s_c_payments_ecf87b2d24_completed_refund_replay_is_a_noop(): void
    {
        $id = 'SC-Payments-ecf87b2d24';
        [$payment, $refund] = $this->refundPayment();
        $before = [DB::table('wallet_ledger')->count(), $this->events, (int) DB::table('bookings')->where('id', $payment->booking_id)->value('amount_paid_minor')];
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('refund')->never();
        $this->app->instance(PaymentGateway::class, $gateway);
        app(ProcessRefundAction::class)->execute($refund->id);
        $this->check($id, 'no repeated gateway financial or event effect', $before, [DB::table('wallet_ledger')->count(), $this->events, (int) DB::table('bookings')->where('id', $payment->booking_id)->value('amount_paid_minor')]);
        $this->check($id, 'completed remains', 'completed', $refund->fresh()->status->value);
    }

    private function ledgerInput(string $key, int $debit = 10000, int $credit = 10000, string $currency = 'EGP'): PostLedgerTransactionInput
    {
        return new PostLedgerTransactionInput(TransactionKind::ManualAdjustment, $currency, $key, 'risk-pilot', null, 'system', null, [
            new LedgerEntryInput('platform_account', SuspenseAccount::PlatformAdjustments->value, LedgerDirection::Debit, $debit, LedgerEntryType::ManualAdjustmentDebit),
            new LedgerEntryInput('platform_account', SuspenseAccount::PlatformClearing->value, LedgerDirection::Credit, $credit, LedgerEntryType::ManualAdjustmentCredit),
        ]);
    }

    private function imbalance(): int
    {
        return (int) DB::table('wallet_ledger')->where('direction', 'debit')->sum('amount_minor') - (int) DB::table('wallet_ledger')->where('direction', 'credit')->sum('amount_minor');
    }

    private function ledgerSnapshot(): array
    {
        return [DB::table('wallet_ledger')->orderBy('id')->get()->toJson(), DB::table('ledger_transaction_groups')->count(), Wallet::orderBy('id')->get(['id', 'balance_minor', 'pending_withdrawal_minor'])->toJson(), $this->events];
    }

    public function test_s_c_settlement_f0a715e615_balanced_transaction_is_atomic_and_projected(): void
    {
        $id = 'SC-Settlement-f0a715e615';
        $result = app(LedgerWriter::class)->post($this->ledgerInput('balanced'));
        $this->check($id, 'one group', 1, DB::table('ledger_transaction_groups')->count());
        $this->check($id, 'two entries', 2, DB::table('wallet_ledger')->where('transaction_group_id', $result->groupId)->count());
        $this->check($id, 'balanced minor units', 0, $this->imbalance());
        $this->check($id, 'debit wallet projection', -10000, Wallet::where('owner_type', 'platform_account')->where('owner_id', SuspenseAccount::PlatformAdjustments->value)->first()->balance_minor);
        $this->check($id, 'credit wallet projection', 10000, Wallet::where('owner_type', 'platform_account')->where('owner_id', SuspenseAccount::PlatformClearing->value)->first()->balance_minor);
        $this->check($id, 'after commit event', [LedgerTransactionPosted::class], $this->events);
    }

    public function test_s_c_settlement_b02f55e5d5_exact_replay_preserves_immutable_rows(): void
    {
        $id = 'SC-Settlement-b02f55e5d5';
        $ledger = app(LedgerWriter::class);
        $first = $ledger->post($this->ledgerInput('exact-replay'));
        $before = $this->ledgerSnapshot();
        $second = $ledger->post($this->ledgerInput('exact-replay'));
        $this->check($id, 'same group', $first->groupId, $second->groupId);
        $this->check($id, 'rows projections events unchanged', $before, $this->ledgerSnapshot());
    }

    public function test_s_c_settlement_25a3403605_changed_replay_rejected_without_side_effect(): void
    {
        $id = 'SC-Settlement-25a3403605';
        $ledger = app(LedgerWriter::class);
        $ledger->post($this->ledgerInput('conflicting'));
        $before = $this->ledgerSnapshot();
        $exception = null;
        try {
            $ledger->post($this->ledgerInput('conflicting', 9999, 9999));
        } catch (Throwable $error) {
            $exception = $error::class;
        }
        $this->check($id, 'specific payload conflict', DuplicateIdempotencyKeyWithDifferentPayloadException::class, $exception);
        $this->check($id, 'no financial/event changes', $before, $this->ledgerSnapshot());
    }

    public function test_s_c_settlement_71a4330a50_unbalanced_transaction_creates_no_posting(): void
    {
        $id = 'SC-Settlement-71a4330a50';
        $exception = null;
        try {
            app(LedgerWriter::class)->post($this->ledgerInput('unbalanced', 10000, 9999));
        } catch (Throwable $error) {
            $exception = $error::class;
        }
        $this->check($id, 'unbalanced guard', UnbalancedTransactionException::class, $exception);
        $this->check($id, 'no group', 0, DB::table('ledger_transaction_groups')->count());
        $this->check($id, 'no entries', 0, DB::table('wallet_ledger')->count());
        $this->check($id, 'no event', [], $this->events);
    }

    public function test_s_c_settlement_fc80457948_currency_mismatch_preserves_existing_projection(): void
    {
        $id = 'SC-Settlement-fc80457948';
        app(LedgerWriter::class)->post($this->ledgerInput('egp'));
        $before = $this->ledgerSnapshot();
        $exception = null;
        try {
            app(LedgerWriter::class)->post($this->ledgerInput('usd', 10000, 10000, 'USD'));
        } catch (Throwable $error) {
            $exception = $error::class;
        }
        $this->check($id, 'wallet currency rejected', WalletCurrencyMismatchException::class, $exception);
        $this->check($id, 'no mutations', $before, $this->ledgerSnapshot());
    }

    public function test_s_c_booking_320197067b_two_processes_cannot_oversell_one_unit(): void
    {
        $id = 'SC-Booking-320197067b';
        $vendor = VendorProfile::factory()->approved()->create();
        $service = Service::factory()->sale()->published()->create(['vendor_profile_id' => $vendor->id, 'base_price_minor' => 10000, 'base_price_currency' => 'EGP']);
        ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 1]);
        self::assertSame(1, app(CatalogServiceReader::class)->findPublishedById($service->id)->stockQuantity, 'Real contract sees finite stock');
        $processes = [];
        $bookings = [];
        foreach ([0, 1] as $index) {
            $booking = Booking::factory()->create();
            $bookings[] = $booking;
            $process = new Process([PHP_BINARY, base_path('qa-audit/risk-program/tests/inventory-worker.php'), (string) $booking->id, (string) $booking->customer_id, (string) $service->id, (string) $index]);
            $process->setTimeout(60);
            $process->start();
            $processes[] = $process;
        }
        $deadline = microtime(true) + 30;
        while ((! file_exists(getenv('QA_OUTPUT').'/worker-0.ready') || ! file_exists(getenv('QA_OUTPUT').'/worker-1.ready')) && microtime(true) < $deadline) {
            usleep(10000);
        }
        self::assertTrue(file_exists(getenv('QA_OUTPUT').'/worker-0.ready') && file_exists(getenv('QA_OUTPUT').'/worker-1.ready'), 'Both workers bootstrapped');
        DB::beginTransaction();
        DB::table('services')->where('id', $service->id)->lockForUpdate()->first();
        touch(getenv('QA_OUTPUT').'/inventory.go');
        // Force both independent workers to contend for the real service lock.
        usleep(250000);
        DB::commit();
        $statuses = [];
        foreach ($processes as $process) {
            $process->wait();
            self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $statuses[] = $this->inventoryWorkerStatus($process);
        }
        sort($statuses);
        $this->evidence[$id]['worker_statuses'] = $statuses;
        $this->check($id, 'capacity preserved in committed reservations', 1, (int) DB::table('service_inventory_reservations')->where('service_id', $service->id)->where('status', 'held')->sum('quantity'));
        $this->check($id, 'one accepts one inventory conflict', [201, 409], $statuses);
        $this->check($id, 'only one item persisted', 1, DB::table('booking_items')->where('service_id', $service->id)->count());
        $this->check($id, 'only one held unit', 1, (int) DB::table('service_inventory_reservations')->where('service_id', $service->id)->where('status', 'held')->sum('quantity'));
    }

    public function test_s_c_booking_rq_a002_two_processes_cannot_double_reserve_rental_window(): void
    {
        $id = 'SC-Booking-RQA002-rental';
        $vendor = VendorProfile::factory()->approved()->create();
        $service = Service::factory()->rental()->published()->create([
            'vendor_profile_id' => $vendor->id,
            'base_price_minor' => 10000,
            'base_price_currency' => 'EGP',
        ]);
        ServiceRentalDetail::factory()->create([
            'service_id' => $service->id,
            'default_rental_duration_hours' => 4,
        ]);
        $startsAt = now()->addDays(14)->startOfHour();
        $endsAt = $startsAt->copy()->addHours(4);
        $bookings = [];
        foreach ([0, 1] as $_) {
            $bookings[] = Booking::factory()->draft()->create([
                'event_starts_at' => $startsAt,
                'event_ends_at' => $endsAt,
            ]);
        }

        $statuses = $this->runInventoryWorkers($service, $bookings, 'rental', $startsAt->toISOString(), $endsAt->toISOString());

        $this->check($id, 'one accepts one rental window conflict', [201, 409], $statuses);
        $this->check($id, 'one overlapping rental hold', 1, DB::table('service_inventory_reservations')->where('service_id', $service->id)->where('status', 'held')->count());
        $this->check($id, 'one booking item persisted', 1, DB::table('booking_items')->where('service_id', $service->id)->count());
    }

    public function test_s_c_booking_rq_a002_two_modifications_cannot_oversell_one_unit(): void
    {
        $id = 'SC-Booking-RQA002-modification';
        $vendor = VendorProfile::factory()->approved()->create();
        $service = Service::factory()->sale()->published()->create([
            'vendor_profile_id' => $vendor->id,
            'base_price_minor' => 10000,
            'base_price_currency' => 'EGP',
        ]);
        ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 1]);

        $modifications = [];
        foreach ([0, 1] as $_) {
            $customer = User::factory()->phoneVerified()->asCustomer()->create();
            $booking = Booking::factory()->create([
                'customer_id' => $customer->id,
                'lifecycle_status' => CustomerReviewState::class,
            ]);
            $bookingVendor = BookingVendor::factory()->create([
                'booking_id' => $booking->id,
                'vendor_profile_id' => $vendor->id,
                'sub_status' => VendorSubStatus::Modified,
            ]);
            $modification = BookingModification::factory()->create([
                'booking_vendor_id' => $bookingVendor->id,
                'proposed_by' => $vendor->user_id,
                'status' => ModificationStatus::Pending,
                'expires_at' => now()->addHour(),
            ]);
            BookingModificationItem::create([
                'booking_modification_id' => $modification->id,
                'change_kind' => ModificationChangeKind::Add,
                'payload' => [
                    'service_id' => $service->id,
                    'product_type' => 'sale',
                    'unit_price_minor' => 10000,
                    'quantity' => 1,
                ],
            ]);
            $modifications[] = compact('booking', 'customer', 'modification');
        }

        $gate = 'modification-inventory';
        $output = getenv('QA_OUTPUT');
        $processes = [];
        foreach ($modifications as $index => $row) {
            $process = new Process([
                PHP_BINARY,
                base_path('qa-audit/risk-program/tests/inventory-worker.php'),
                (string) $row['booking']->id,
                (string) $row['customer']->id,
                (string) $service->id,
                (string) $index,
                'confirm-modification',
                $gate,
                $row['modification']->public_id,
            ]);
            $process->setTimeout(60);
            $process->start();
            $processes[] = $process;
        }

        $deadline = microtime(true) + 30;
        while ((! file_exists($output.'/'.$gate.'-0.ready') || ! file_exists($output.'/'.$gate.'-1.ready')) && microtime(true) < $deadline) {
            usleep(10000);
        }
        self::assertTrue(file_exists($output.'/'.$gate.'-0.ready') && file_exists($output.'/'.$gate.'-1.ready'), 'Both modification workers bootstrapped');
        DB::beginTransaction();
        DB::table('services')->where('id', $service->id)->lockForUpdate()->first();
        touch($output.'/'.$gate.'.go');
        try {
            usleep(250000);
        } finally {
            DB::commit();
        }

        $statuses = [];
        foreach ($processes as $process) {
            $process->wait();
            self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $statuses[] = $this->inventoryWorkerStatus($process);
        }
        sort($statuses);

        $this->check($id, 'one modification succeeds and one hits stock conflict', [200, 409], $statuses);
        $this->check($id, 'one sale reservation persisted', 1, (int) DB::table('service_inventory_reservations')->where('service_id', $service->id)->where('status', 'held')->sum('quantity'));
        $this->check($id, 'one modification item persisted', 1, DB::table('booking_items')->where('service_id', $service->id)->count());
    }

    /** @param array<int,Booking> $bookings @return array<int,int> */
    private function runInventoryWorkers(
        Service $service,
        array $bookings,
        string $gate,
        ?string $startsAt = null,
        ?string $endsAt = null,
    ): array {
        $output = getenv('QA_OUTPUT');
        $processes = [];
        foreach ($bookings as $index => $booking) {
            $process = new Process([
                PHP_BINARY,
                base_path('qa-audit/risk-program/tests/inventory-worker.php'),
                (string) $booking->id,
                (string) $booking->customer_id,
                (string) $service->id,
                (string) $index,
                'add-item',
                $gate,
                $startsAt ?? '',
                $endsAt ?? '',
            ]);
            $process->setTimeout(60);
            $process->start();
            $processes[] = $process;
        }

        $deadline = microtime(true) + 30;
        while ((! file_exists($output.'/'.$gate.'-0.ready') || ! file_exists($output.'/'.$gate.'-1.ready')) && microtime(true) < $deadline) {
            usleep(10000);
        }
        self::assertTrue(file_exists($output.'/'.$gate.'-0.ready') && file_exists($output.'/'.$gate.'-1.ready'), 'Both workers bootstrapped');
        DB::beginTransaction();
        DB::table('services')->where('id', $service->id)->lockForUpdate()->first();
        touch($output.'/'.$gate.'.go');
        try {
            usleep(250000);
        } finally {
            DB::commit();
        }

        $statuses = [];
        foreach ($processes as $process) {
            $process->wait();
            self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $statuses[] = $this->inventoryWorkerStatus($process);
        }
        sort($statuses);

        return $statuses;
    }

    private function inventoryWorkerStatus(Process $process): int
    {
        $output = trim($process->getOutput());

        try {
            $status = json_decode($output, true, flags: JSON_THROW_ON_ERROR)['status'] ?? null;
        } catch (JsonException) {
            self::fail(sprintf(
                'Inventory worker returned invalid JSON (exit %s). stdout=%s stderr=%s',
                $process->getExitCode() ?? 'unknown',
                var_export(substr($output, 0, 2000), true),
                var_export(substr($process->getErrorOutput(), 0, 2000), true),
            ));
        }

        self::assertIsInt($status, 'Inventory worker status must be an HTTP status code.');

        return $status;
    }
}

<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Application\DTOs\RefundResultDto;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Filament\Resources\PaymentResource\Pages\ViewPayment;
use Brick\Money\Money;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function grantRefundPaymentPermission(User $user): void
{
    $user->givePermissionTo(Permission::findOrCreate('payment.refund', 'web'));
}

function createRefundHeaderPayment(int $amountMinor = 10_000): Payment
{
    $booking = Booking::factory()->create([
        'subtotal_minor' => $amountMinor,
        'subtotal_currency' => 'EGP',
        'discount_total_minor' => 0,
        'discount_total_currency' => 'EGP',
        'loyalty_redeemed_minor' => 0,
        'loyalty_redeemed_currency' => 'EGP',
    ]);

    return Payment::factory()->captured()->create([
        'booking_id' => $booking->id,
        'amount_minor' => $amountMinor,
        'amount_currency' => 'EGP',
    ]);
}

it('shows the payment header refund action only for permitted captured payments', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $payment = createRefundHeaderPayment();
    $admin = User::factory()->asAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(ViewPayment::class, ['record' => $payment->public_id])
        ->assertSuccessful()
        ->assertActionHidden('refund');

    grantRefundPaymentPermission($admin);
    Livewire::test(ViewPayment::class, ['record' => $payment->public_id])
        ->assertSuccessful()
        ->assertActionVisible('refund');

    $pendingPayment = Payment::factory()->create(['booking_id' => $payment->booking_id]);
    Livewire::test(ViewPayment::class, ['record' => $pendingPayment->public_id])
        ->assertSuccessful()
        ->assertActionHidden('refund');
});

it('records authorized partial and full refunds through the existing refund flow', function (string $amount, int $expectedMinor): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->asAdmin()->create();
    grantRefundPaymentPermission($admin);
    $this->actingAs($admin);
    $payment = createRefundHeaderPayment();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('refund')->once()
        ->with(Mockery::on(fn (Payment $record): bool => $record->is($payment)), Mockery::on(
            fn (Money $requested): bool => $requested->getMinorAmount()->toInt() === $expectedMinor,
        ))
        ->andReturn(new RefundResultDto(false, null, 'Awaiting provider confirmation.', true));
    app()->instance(PaymentGateway::class, $gateway);

    Livewire::test(ViewPayment::class, ['record' => $payment->public_id])
        ->callAction('refund', data: [
            'amount' => $amount,
            'reason_code' => 'customer_request',
            'reason_notes' => ['en' => 'Customer request', 'ar' => 'طلب العميل'],
            'idempotency_key' => 'refund-test-'.$expectedMinor,
        ])
        ->assertHasNoActionErrors();

    $refund = Refund::query()->sole();
    expect($refund->amount_minor)->toBe($expectedMinor)
        ->and($refund->initiated_by)->toBe($admin->id);
})->with([
    'partial' => ['40.00', 4_000],
    'full' => ['100.00', 10_000],
]);

it('rejects a payment header refund above the refundable limit', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->asAdmin()->create();
    grantRefundPaymentPermission($admin);
    $this->actingAs($admin);
    $payment = createRefundHeaderPayment();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('refund')->never();
    app()->instance(PaymentGateway::class, $gateway);

    Livewire::test(ViewPayment::class, ['record' => $payment->public_id])
        ->callAction('refund', data: [
            'amount' => '100.01',
            'reason_code' => 'customer_request',
            'reason_notes' => ['en' => 'Customer request', 'ar' => 'طلب العميل'],
            'idempotency_key' => 'refund-too-large',
        ])
        ->assertHasActionErrors(['amount']);

    expect(Refund::query()->count())->toBe(0);
});

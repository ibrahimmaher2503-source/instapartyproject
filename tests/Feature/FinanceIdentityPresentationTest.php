<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Http\Resources\RefundResource;
use App\Modules\Settlement\Application\Support\FinanceIdentityPresenter;
use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Http\Resources\ReconciliationFindingResource;
use Illuminate\Http\Request;

it('presents a public financial identity with a localized type and no internal id', function (): void {
    app('translator')->setLocale('ar');
    $wallet = new Wallet(['public_id' => '01WALLET-PUBLIC-IDENTITY']);

    $value = FinanceIdentityPresenter::reference('App\\Modules\\Settlement\\Domain\\Models\\Wallet', 42, $wallet);

    expect($value)
        ->toContain('المحفظة')
        ->toContain('01WALLET-PUBLIC-IDENTITY')
        ->not->toContain('42')
        ->not->toContain('App\\Modules');
});

it('uses a safe localized fallback for a missing or legacy identity', function (): void {
    app('translator')->setLocale('en');

    expect(FinanceIdentityPresenter::reference('wallet', 0))
        ->toBe('Legacy / Unknown')
        ->not->toContain('0');
});

it('exposes refund payment and booking public ids without internal foreign keys', function (): void {
    $refund = new Refund([
        'public_id' => '01REFUND-PUBLIC-IDENTITY',
        'payment_id' => 17,
        'booking_id' => 23,
        'amount_minor' => 1500,
        'amount_currency' => 'EGP',
        'reason_code' => RefundReasonCode::CustomerRequest,
        'status' => RefundStatus::Pending,
    ]);
    $refund->setRelation('payment', new Payment(['public_id' => '01PAYMENT-PUBLIC-IDENTITY']));
    $refund->setRelation('booking', new Booking(['public_id' => '01BOOKING-PUBLIC-IDENTITY']));

    $data = (new RefundResource($refund))->toArray(Request::create('/api/v1/admin/refunds/test', 'GET'));

    expect($data)
        ->toHaveKey('payment_public_id', '01PAYMENT-PUBLIC-IDENTITY')
        ->toHaveKey('booking_public_id', '01BOOKING-PUBLIC-IDENTITY')
        ->not->toHaveKey('payment_id')
        ->not->toHaveKey('booking_id');
});

it('does not serialize a reconciliation finding internal resource id', function (): void {
    $finding = new ReconciliationFinding([
        'public_id' => '01FINDING-PUBLIC-IDENTITY',
        'finding_type' => 'wallet_cache_drift',
        'severity' => 'warning',
        'resource_type' => 'wallet',
        'resource_id' => 0,
    ]);

    $data = (new ReconciliationFindingResource($finding))->toArray(Request::create('/api/v1/admin/settlement/reconciliation/findings', 'GET'));

    expect($data['resource']['public_id'])->toBeNull();
    expect($data)
        ->not->toHaveKey('resource_id')
        ->not->toHaveKey('resource_type');
});

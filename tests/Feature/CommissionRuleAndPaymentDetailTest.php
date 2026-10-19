<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Filament\Resources\PaymentResource\Pages\ViewPayment;
use App\Modules\Settlement\Domain\Models\CommissionRate;
use App\Modules\Settlement\Filament\Resources\CommissionRulesResource;
use App\Modules\Settlement\Filament\Resources\CommissionRulesResource\Pages\CreateCommissionRule;
use App\Modules\Settlement\Filament\Resources\CommissionRulesResource\Pages\EditCommissionRule;
use Brick\Money\Money;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo([
        Permission::findOrCreate('create_commission::rules', 'web'),
        Permission::findOrCreate('view_any_commission::rules', 'web'),
        Permission::findOrCreate('view_commission::rules', 'web'),
        Permission::findOrCreate('update_commission::rules', 'web'),
        Permission::findOrCreate('view_bookings::monitor', 'web'),
    ]);
    $this->actingAs($admin);
    expect($admin->can('create_commission::rules'))->toBeTrue();
    expect(CommissionRulesResource::canCreate())->toBeTrue();
});

it('shows a localized field error for a duplicate wildcard commission scope', function (string $locale): void {
    app()->setLocale($locale);
    CommissionRate::factory()->create([
        'category_id' => null,
        'product_type' => null,
        'commission_bps' => 1500,
    ]);

    Livewire::test(CreateCommissionRule::class)
        ->assertSuccessful()
        ->fillForm([
            'category_id' => null,
            'product_type' => null,
            'commission_bps' => 2000,
            'effective_from' => today()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['commission_bps'])
        ->assertSee(__('settlement.commission_rule.duplicate_scope'));

    expect(CommissionRate::query()->count())->toBe(1);
})->with(['English' => 'en', 'Arabic' => 'ar']);

it('allows a distinct commission scope and prevents editing into an existing scope', function (): void {
    $wildcard = CommissionRate::factory()->create(['category_id' => null, 'product_type' => null]);

    Livewire::test(CreateCommissionRule::class)
        ->fillForm([
            'category_id' => null,
            'product_type' => 'rental',
            'commission_bps' => 2000,
            'effective_from' => today()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rental = CommissionRate::query()->where('product_type', 'rental')->firstOrFail();

    Livewire::test(EditCommissionRule::class, ['record' => $rental->public_id])
        ->fillForm([
            'category_id' => null,
            'product_type' => null,
            'commission_bps' => 2500,
            'effective_from' => today()->toDateString(),
        ])
        ->call('save')
        ->assertHasFormErrors(['commission_bps']);

    expect($rental->fresh()->product_type->value)->toBe('rental')
        ->and($wildcard->fresh()->product_type)->toBeNull();
});

it('shows read-only payment, booking and VAT details with public identifiers', function (string $locale, string $itemName, string $paymentCurrency): void {
    app()->setLocale($locale);
    $customer = User::factory()->asCustomer()->create(['name' => 'Payment Detail Customer']);
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'reference_no' => 'BK-PAYMENT-DETAIL',
        'total_currency' => 'EGP',
        'total_vat_minor' => 11900,
    ]);
    $vendor = BookingVendor::factory()->create(['booking_id' => $booking->id]);
    BookingItem::factory()->create([
        'booking_vendor_id' => $vendor->id,
        'name_snapshot' => ['en' => 'Decor package', 'ar' => 'باقة زينة'],
        'vat_rate_bps' => 1400,
        'vat_amount_minor' => 11900,
        'vat_amount_currency' => 'EGP',
    ]);
    $payment = Payment::factory()->captured()->create([
        'booking_id' => $booking->id,
        'amount_minor' => 85000,
        'amount_currency' => $paymentCurrency,
        'gateway_ref' => 'PAY-DETAIL-REF',
    ]);

    Livewire::test(ViewPayment::class, ['record' => $payment->public_id])
        ->assertSuccessful()
        ->assertSeeText($payment->public_id)
        ->assertSeeText(Money::ofMinor(85000, $paymentCurrency)->formatTo($locale))
        ->assertSeeText('PAY-DETAIL-REF')
        ->assertSeeText('BK-PAYMENT-DETAIL')
        ->assertSee(route('filament.admin.resources.bookings.view', ['record' => $booking->public_id]), false)
        ->assertSeeText($booking->public_id)
        ->assertSeeText('Payment Detail Customer')
        ->assertSeeText($itemName)
        ->assertSeeText('1400 bps')
        ->assertSeeText(Money::ofMinor(11900, 'EGP')->formatTo($locale));
})->with([
    'English EGP' => ['en', 'Decor package', 'EGP'],
    'Arabic EGP' => ['ar', 'باقة زينة', 'EGP'],
    'English USD' => ['en', 'Decor package', 'USD'],
]);

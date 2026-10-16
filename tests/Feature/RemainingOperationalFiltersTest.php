<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Resources\BookingResource\Pages\ListBookings;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Filament\Resources\PaymentResource;
use App\Modules\Payments\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Modules\Payments\Filament\Resources\RefundResource\Pages\ListRefunds;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Filament\Resources\WithdrawalResource\Pages\ListWithdrawals;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('shows saved views on permitted operational lists with page and user scoped storage', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/payments')
        ->assertOk()
        ->assertSee('data-booking-monitor-saved-views', false)
        ->assertSee('instaparty.admin.payments.views.'.$admin->id.'.v1');
});

it('keeps saved views off operational lists the user cannot view', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $this->actingAs($customer, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/payments')->assertForbidden();
});

it('exposes amount and vendor filters on permitted payment lists', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $filters = Livewire::test(ListPayments::class)->instance()->getTable()->getFilters();

    expect($filters)->toHaveKeys(['amount', 'vendor_id'])
        ->and(PaymentResource::canViewAny())->toBeTrue();
});

it('filters payments by EGP amount and their booking vendor', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $allocation = BookingVendor::factory()->create();
    $matchingVendorPayment = Payment::factory()->create([
        'booking_id' => $allocation->booking_id,
        'amount_minor' => 7500,
    ]);
    $otherPayment = Payment::factory()->create(['amount_minor' => 15000]);
    $foreignCurrencyPayment = Payment::factory()->create(['amount_minor' => 7500, 'amount_currency' => 'USD']);

    Livewire::test(ListPayments::class)
        ->filterTable('amount', ['min' => '50', 'max' => '100'])
        ->assertCanSeeTableRecords([$matchingVendorPayment])
        ->assertCanNotSeeTableRecords([$otherPayment, $foreignCurrencyPayment]);

    Livewire::test(ListPayments::class)
        ->filterTable('vendor_id', $allocation->vendor_profile_id)
        ->assertCanSeeTableRecords([$matchingVendorPayment])
        ->assertCanNotSeeTableRecords([$otherPayment]);
});

it('exposes vendor and amount filters on bookings, refunds, and withdrawals', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $admin->givePermissionTo([
        Permission::findOrCreate('view_any_refund', 'web'),
        Permission::findOrCreate('view_any_withdrawals::queue', 'web'),
        Permission::findOrCreate('view_withdrawals::queue', 'web'),
    ]);
    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    expect(Livewire::test(ListBookings::class)->instance()->getTable()->getFilters())->toHaveKeys(['vendor_id', 'amount'])
        ->and(Livewire::test(ListRefunds::class)->instance()->getTable()->getFilters())->toHaveKeys(['vendor_id', 'amount'])
        ->and(Livewire::test(ListWithdrawals::class)->instance()->getTable()->getFilters())->toHaveKeys(['vendorProfile', 'amount']);
});

it('filters bookings, refunds, and withdrawals by EGP amount ranges and vendor', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $admin->givePermissionTo([
        Permission::findOrCreate('view_any_refund', 'web'),
        Permission::findOrCreate('view_any_withdrawals::queue', 'web'),
        Permission::findOrCreate('view_withdrawals::queue', 'web'),
    ]);
    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $allocation = BookingVendor::factory()->create();
    $booking = Booking::query()->findOrFail($allocation->booking_id);
    $booking->forceFill(['total_minor' => 7500, 'total_currency' => 'EGP'])->save();
    $otherBooking = Booking::factory()->create(['total_minor' => 15000, 'total_currency' => 'EGP']);

    Livewire::test(ListBookings::class)
        ->filterTable('amount', ['min' => '50', 'max' => '100'])
        ->assertCanSeeTableRecords([$booking])
        ->assertCanNotSeeTableRecords([$otherBooking]);
    Livewire::test(ListBookings::class)
        ->filterTable('vendor_id', $allocation->vendor_profile_id)
        ->assertCanSeeTableRecords([$booking])
        ->assertCanNotSeeTableRecords([$otherBooking]);

    $refund = Refund::factory()->create(['booking_id' => $booking->id, 'amount_minor' => 7500, 'amount_currency' => 'EGP']);
    $otherRefund = Refund::factory()->create(['booking_id' => $otherBooking->id, 'amount_minor' => 15000, 'amount_currency' => 'EGP']);
    Livewire::test(ListRefunds::class)
        ->filterTable('amount', ['min' => '50', 'max' => '100'])
        ->assertCanSeeTableRecords([$refund])
        ->assertCanNotSeeTableRecords([$otherRefund]);
    Livewire::test(ListRefunds::class)
        ->filterTable('vendor_id', $allocation->vendor_profile_id)
        ->assertCanSeeTableRecords([$refund])
        ->assertCanNotSeeTableRecords([$otherRefund]);

    $withdrawal = Withdrawal::factory()->create([
        'vendor_profile_id' => $allocation->vendor_profile_id,
        'requested_amount_minor' => 7500,
        'requested_amount_currency' => 'EGP',
    ]);
    $otherWithdrawal = Withdrawal::factory()->create(['requested_amount_minor' => 15000, 'requested_amount_currency' => 'EGP']);
    Livewire::test(ListWithdrawals::class)
        ->filterTable('amount', ['min' => '50', 'max' => '100'])
        ->assertCanSeeTableRecords([$withdrawal])
        ->assertCanNotSeeTableRecords([$otherWithdrawal]);
    Livewire::test(ListWithdrawals::class)
        ->filterTable('vendorProfile', $allocation->vendor_profile_id)
        ->assertCanSeeTableRecords([$withdrawal])
        ->assertCanNotSeeTableRecords([$otherWithdrawal]);
});

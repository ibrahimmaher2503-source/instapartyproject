<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Filament\Resources\PaymentResource;
use App\Modules\Payments\Filament\Resources\PaymentResource\Pages\ListPayments;
use Filament\Facades\Filament;
use Livewire\Livewire;

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

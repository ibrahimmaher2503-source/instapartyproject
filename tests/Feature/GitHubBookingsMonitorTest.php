<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Resources\BookingsMonitorResource\Pages\ListBookingsMonitor;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Filament\Auth\IsolatedPanelLogin;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('renders the bookings monitor after an authorized admin signs in', function (): void {
    Role::findOrCreate('admin', 'web');

    $password = 'SafePassword123!';
    $admin = User::factory()->asAdmin()->create([
        'email' => 'github101-admin@example.test',
        'password' => $password,
    ]);
    $admin->givePermissionTo(Permission::findOrCreate('view_any_bookings::monitor', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $booking = Booking::factory()->create([
        'lifecycle_status' => LifecycleStatus::VendorReview->value,
    ]);
    BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'sub_status' => VendorSubStatus::Pending->value,
        'response_deadline' => now('UTC')->subMinute(),
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(IsolatedPanelLogin::class)
        ->fillForm(['email' => $admin->email, 'password' => $password])
        ->call('authenticate')
        ->assertRedirect(Filament::getPanel('admin')->getUrl());

    expect(auth('web')->id())->toBe($admin->id);

    $this->get('/admin/bookings-monitor')
        ->assertOk()
        ->assertSee($booking->reference_no);

    Livewire::test(ListBookingsMonitor::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$booking]);
});

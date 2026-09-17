<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Filament\Resources\AdminBookingInterventionResource\Pages\ListBookingInterventions;
use App\Modules\Identity\Domain\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('localizes lifecycle states in the Arabic intervention table', function (): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
    ]);
    BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'sub_status' => 'pending',
        'response_deadline' => CarbonImmutable::now('UTC')->subMinute(),
    ]);

    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('booking.intervene.access', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    app()->setLocale('ar');

    Livewire::test(ListBookingInterventions::class)
        ->assertCanSeeTableRecords([$booking])
        ->assertTableColumnFormattedStateSet('lifecycle_status', 'قيد مراجعة المورد', $booking);
});

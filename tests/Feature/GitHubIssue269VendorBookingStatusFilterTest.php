<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingsPage;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Livewire\Attributes\Url;
use Livewire\Livewire;

function issue269ApprovedVendorUser(): array
{
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $profile = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);

    return [$user, $profile];
}

it('has Livewire Url attribute configured on statusFilter property', function (): void {
    $reflection = new ReflectionProperty(VendorBookingsPage::class, 'statusFilter');
    $attributes = $reflection->getAttributes(Url::class);

    expect($attributes)->toHaveCount(1);
    $instance = $attributes[0]->newInstance();
    expect($instance->as)->toBe('statusFilter')
        ->and($instance->keep)->toBeTrue();
});

it('hydrates statusFilter from query string on mount and renders matching bookings', function (string $statusKey, VendorSubStatus $subStatus): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    [$user, $profile] = issue269ApprovedVendorUser();

    $bookingPending = Booking::factory()->create(['reference_no' => 'BK-TEST-PENDING']);
    $pendingRecord = BookingVendor::factory()->create([
        'booking_id' => $bookingPending->id,
        'vendor_profile_id' => $profile->id,
        'sub_status' => VendorSubStatus::Pending,
    ]);

    $bookingTarget = Booking::factory()->create(['reference_no' => 'BK-TEST-TARGET-'.$statusKey]);
    $targetRecord = BookingVendor::factory()->create([
        'booking_id' => $bookingTarget->id,
        'vendor_profile_id' => $profile->id,
        'sub_status' => $subStatus,
    ]);

    $this->actingAs($user, 'vendor');

    Livewire::withQueryParams(['statusFilter' => $statusKey])
        ->test(VendorBookingsPage::class)
        ->assertSet('statusFilter', $statusKey)
        ->assertCanSeeTableRecords([$targetRecord])
        ->assertCanNotSeeTableRecords([$pendingRecord]);
})->with([
    'accepted' => ['accepted', VendorSubStatus::Accepted],
    'in_progress' => ['in_progress', VendorSubStatus::InProgress],
    'completed' => ['completed', VendorSubStatus::Completed],
    'rejected' => ['rejected', VendorSubStatus::Rejected],
]);

it('falls back safely to pending when statusFilter query parameter is invalid', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    [$user, $profile] = issue269ApprovedVendorUser();

    $bookingPending = Booking::factory()->create(['reference_no' => 'BK-TEST-PENDING-FALLBACK']);
    $pendingRecord = BookingVendor::factory()->create([
        'booking_id' => $bookingPending->id,
        'vendor_profile_id' => $profile->id,
        'sub_status' => VendorSubStatus::Pending,
    ]);

    $bookingCompleted = Booking::factory()->create(['reference_no' => 'BK-TEST-COMPLETED-FALLBACK']);
    $completedRecord = BookingVendor::factory()->create([
        'booking_id' => $bookingCompleted->id,
        'vendor_profile_id' => $profile->id,
        'sub_status' => VendorSubStatus::Completed,
    ]);

    $this->actingAs($user, 'vendor');

    Livewire::withQueryParams(['statusFilter' => 'unknown_status_injection'])
        ->test(VendorBookingsPage::class)
        ->assertSet('statusFilter', 'pending')
        ->assertCanSeeTableRecords([$pendingRecord])
        ->assertCanNotSeeTableRecords([$completedRecord]);
});

it('switches tabs and resets table records via setStatusFilter and updatedStatusFilter', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    [$user, $profile] = issue269ApprovedVendorUser();

    $bookingPending = Booking::factory()->create(['reference_no' => 'BK-TEST-PENDING-SWITCH']);
    $pendingRecord = BookingVendor::factory()->create([
        'booking_id' => $bookingPending->id,
        'vendor_profile_id' => $profile->id,
        'sub_status' => VendorSubStatus::Pending,
    ]);

    $bookingCompleted = Booking::factory()->create(['reference_no' => 'BK-TEST-COMPLETED-SWITCH']);
    $completedRecord = BookingVendor::factory()->create([
        'booking_id' => $bookingCompleted->id,
        'vendor_profile_id' => $profile->id,
        'sub_status' => VendorSubStatus::Completed,
    ]);

    $this->actingAs($user, 'vendor');

    $component = Livewire::test(VendorBookingsPage::class)
        ->assertSet('statusFilter', 'pending')
        ->assertCanSeeTableRecords([$pendingRecord])
        ->assertCanNotSeeTableRecords([$completedRecord]);

    $component->call('setStatusFilter', 'completed')
        ->assertSet('statusFilter', 'completed')
        ->assertCanSeeTableRecords([$completedRecord])
        ->assertCanNotSeeTableRecords([$pendingRecord]);

    $component->set('statusFilter', 'pending')
        ->assertSet('statusFilter', 'pending')
        ->assertCanSeeTableRecords([$pendingRecord])
        ->assertCanNotSeeTableRecords([$completedRecord]);
});

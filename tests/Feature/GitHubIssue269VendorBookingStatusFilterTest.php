<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingsPage;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Livewire\Livewire;

function issue269VendorBookingsVendor(): VendorProfile
{
    $vendor = VendorProfile::factory()->approved()->create();
    Filament::setCurrentPanel(Filament::getPanel('vendor'));

    return $vendor;
}

it('falls back to pending for an unknown URL status instead of showing every booking', function (): void {
    $vendor = issue269VendorBookingsVendor();
    $pending = BookingVendor::factory()->pending()->create(['vendor_profile_id' => $vendor->id]);
    $completed = BookingVendor::factory()->completed()->create(['vendor_profile_id' => $vendor->id]);
    $this->actingAs($vendor->user, 'vendor');

    Livewire::withQueryParams(['statusFilter' => 'unknown'])
        ->test(VendorBookingsPage::class)
        ->assertSet('statusFilter', 'pending')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$completed]);
});

it('resets table pagination when the status changes', function (): void {
    $vendor = issue269VendorBookingsVendor();
    $pending = BookingVendor::factory()->pending()->create(['vendor_profile_id' => $vendor->id]);
    $completed = BookingVendor::factory()->completed()->create(['vendor_profile_id' => $vendor->id]);
    $this->actingAs($vendor->user, 'vendor');

    Livewire::test(VendorBookingsPage::class)
        ->set('paginators.page', 2)
        ->assertSet('paginators.page', 2)
        ->set('statusFilter', 'completed')
        ->assertSet('statusFilter', 'completed')
        ->assertSet('paginators.page', 1)
        ->assertCanSeeTableRecords([$completed])
        ->assertCanNotSeeTableRecords([$pending]);
});

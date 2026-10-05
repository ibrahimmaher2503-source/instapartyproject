<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingDetailPage;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
});

function createApprovedBookingVendor(): array
{
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    $booking = Booking::factory()->create();
    $bookingVendor = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
    ]);

    return [$user, $vendor, $bookingVendor];
}

it('opens an owned booking vendor from the generated detail URL', function (): void {
    [$user, , $bookingVendor] = createApprovedBookingVendor();

    $url = VendorBookingDetailPage::getUrl(['bookingVendor' => $bookingVendor->public_id]);

    expect($url)->toContain('bookingVendor='.$bookingVendor->public_id);

    $this->actingAs($user, 'vendor')
        ->get($url)
        ->assertOk()
        ->assertSeeText($bookingVendor->booking->reference_no);
});

it('returns forbidden rather than not found for another vendor booking', function (): void {
    [$user] = createApprovedBookingVendor();
    [, , $otherBookingVendor] = createApprovedBookingVendor();

    $url = VendorBookingDetailPage::getUrl(['bookingVendor' => $otherBookingVendor->public_id]);

    $this->actingAs($user, 'vendor')
        ->get($url)
        ->assertForbidden();
});

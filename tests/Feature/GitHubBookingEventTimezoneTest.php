<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Resources\BookingResource\Pages\ViewBooking;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingDetailPage;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('labels customer event times using their configured zone without changing UTC persistence', function (string $locale): void {
    $customer = User::factory()->phoneVerified()->asCustomer()->create(['timezone' => 'Asia/Dubai']);
    $booking = Booking::factory()->create(['customer_id' => $customer->id, 'event_starts_at' => '2026-12-01 13:00:00']);
    $this->actingAs($customer)->get("/{$locale}/account/bookings/{$booking->public_id}")->assertOk()
        ->assertSee('Asia/Dubai')->assertSee('17:00');
    $this->get("/{$locale}/account/bookings")->assertOk()->assertSee('Asia/Dubai')->assertSee('17:00');
    expect(DB::table('bookings')->where('id', $booking->id)->value('event_starts_at'))->toBe('2026-12-01 13:00:00');
})->with(['en', 'ar']);

it('labels the vendor event in their configured zone without changing UTC persistence', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $user = User::factory()->phoneVerified()->asVendor()->create(['timezone' => 'Asia/Dubai']);
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    $booking = Booking::factory()->create(['event_starts_at' => '2026-12-01 13:00:00']);
    $allocation = BookingVendor::factory()->create(['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id]);
    $this->actingAs($user, 'vendor')->get(VendorBookingDetailPage::getUrl(['bookingVendor' => $allocation->public_id]))
        ->assertOk()->assertSee('Asia/Dubai')->assertSee('17:00');
    expect(DB::table('bookings')->where('id', $booking->id)->value('event_starts_at'))->toBe('2026-12-01 13:00:00');
});

it('labels the admin event in their configured zone without changing UTC persistence', function (string $locale, string $displayTime): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->phoneVerified()->asAdmin()->create(['timezone' => 'Asia/Dubai']);
    $admin->givePermissionTo(Permission::findOrCreate('view_bookings::monitor', 'web'));
    $booking = Booking::factory()->create(['event_starts_at' => '2026-12-01 13:00:00']);
    $this->actingAs($admin);
    Livewire::test(ViewBooking::class, ['record' => $booking->public_id])
        ->assertSuccessful()->assertSee('Asia/Dubai')->assertSee($displayTime);
    expect(DB::table('bookings')->where('id', $booking->id)->value('event_starts_at'))->toBe('2026-12-01 13:00:00');
})->with([['en', '5:00 PM'], ['ar', '5:00 م']]);

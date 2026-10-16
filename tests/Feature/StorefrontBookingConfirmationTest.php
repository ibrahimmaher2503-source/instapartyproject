<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\SubmitBookingAction;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Events\BookingSubmittedToVendor;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\Event;

it('redirects to a booking committed before a post-submit failure', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->draft()->create(['customer_id' => $customer->id]);
    $action = Mockery::mock(SubmitBookingAction::class);
    $action->shouldReceive('execute')->once()->andReturnUsing(function () use ($booking): never {
        $booking->forceFill(['lifecycle_status' => LifecycleStatus::VendorReview->value])->save();
        throw new RuntimeException('post-commit listener failed');
    });
    $this->instance(SubmitBookingAction::class, $action);

    $this->actingAs($customer)->post("/en/cart/{$booking->public_id}/submit")
        ->assertRedirect("/en/account/bookings/{$booking->public_id}")
        ->assertSessionHas('booking_submitted', $booking->public_id);
    expect($booking->fresh()->lifecycle_status->getValue())->toBe('vendor_review');
});

it('rethrows a submit failure when no booking was committed', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->draft()->create(['customer_id' => $customer->id]);
    $action = Mockery::mock(SubmitBookingAction::class);
    $action->shouldReceive('execute')->once()->andThrow(new RuntimeException('creation failed'));
    $this->instance(SubmitBookingAction::class, $action);

    $this->actingAs($customer)->post("/en/cart/{$booking->public_id}/submit")
        ->assertServerError()->assertSessionMissing('booking_submitted');
    expect($booking->fresh()->lifecycle_status->getValue())->toBe('draft');
});

it('takes a successful submission to its booking with a reference and confirmation', function (string $locale, string $productType): void {
    Event::fake([BookingSubmittedToVendor::class]);
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->draft()->create(['customer_id' => $customer->id]);
    BookingAddress::factory()->create(['booking_id' => $booking->id]);
    $vendor = BookingVendor::factory()->create(['booking_id' => $booking->id]);
    $item = BookingItem::factory()->{$productType}()->create(['booking_vendor_id' => $vendor->id]);
    $item->service->category->occasions()->attach($booking->occasion_id);

    $url = "/{$locale}/account/bookings/{$booking->public_id}";
    $this->actingAs($customer)->post("/{$locale}/cart/{$booking->public_id}/submit")
        ->assertRedirect($url)->assertSessionHas('booking_submitted', $booking->public_id);
    expect($booking->fresh()->lifecycle_status->getValue())->toBe('vendor_review');
    $this->get($url)->assertOk()->assertSee('data-booking-confirmation', false)
        ->assertSee($booking->reference_no)->assertSee(__('account.booking_submitted'))
        ->assertSee("/{$locale}/account/bookings", false)->assertDontSee(__('storefront.cart.no_items'));
})->with(['en', 'ar'])->with(['rental', 'sale', 'digital']);

it('keeps failed submissions in the cart with errors and no success state', function (string $locale, bool $withItem): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->draft()->create(['customer_id' => $customer->id]);
    if ($withItem) {
        $vendor = BookingVendor::factory()->create(['booking_id' => $booking->id]);
        $item = BookingItem::factory()->create(['booking_vendor_id' => $vendor->id]);
        $item->service->category->occasions()->attach($booking->occasion_id);
    }

    $this->actingAs($customer)->post("/{$locale}/cart/{$booking->public_id}/submit")
        ->assertRedirect("/{$locale}/cart")->assertSessionHasErrors('booking')
        ->assertSessionMissing('booking_submitted');
    expect($booking->fresh()->lifecycle_status->getValue())->toBe('draft');
    $this->get("/{$locale}/cart")->assertOk()->assertSee('role="alert"', false)
        ->assertDontSee('data-booking-confirmation', false);
})->with(['en', 'ar'])->with([false, true]);

it('does not show success when an already submitted booking is submitted again', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->create(['customer_id' => $customer->id, 'lifecycle_status' => 'vendor_review']);
    $this->actingAs($customer)->post("/{$locale}/cart/{$booking->public_id}/submit")
        ->assertRedirect("/{$locale}/cart")->assertSessionHasErrors('booking')
        ->assertSessionMissing('booking_submitted');
    $this->get("/{$locale}/cart")->assertOk()->assertSee(__('account.booking_submit_conflict'))
        ->assertDontSee('data-booking-confirmation', false);
})->with(['en', 'ar']);

it('shows saved delivery and event details only to the owning customer', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create(['timezone' => 'Africa/Cairo']);
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'guest_count' => 37,
        'event_ends_at' => '2026-11-10 19:45:00',
    ]);
    $city = City::factory()->create(['name' => ['en' => 'Booking City', 'ar' => 'مدينة الحجز']]);
    BookingAddress::factory()->create([
        'booking_id' => $booking->id,
        'city_id' => $city->id,
        'address_line' => 'Saved street <script>alert(1)</script>',
        'recipient_name' => 'Saved Recipient',
        'recipient_phone_e164' => '+201001234567',
        'building' => 'Building 9',
        'floor' => '0',
        'apartment' => 'Flat 2',
        'landmark' => 'Near the library',
    ]);

    $this->actingAs($customer)->get("/{$locale}/account/bookings/{$booking->public_id}")
        ->assertOk()->assertSee('Saved street <script>alert(1)</script>')
        ->assertDontSee('<script>alert(1)</script>', false)->assertSee('Saved Recipient')
        ->assertSee('+201001234567')->assertSee('dir="ltr"', false)
        ->assertSee($city->getTranslation('name', $locale))->assertSee('37')->assertSee('21:45')
        ->assertSee('Building 9')->assertSee('Flat 2')->assertSee('Near the library');
    $this->actingAs(User::factory()->asCustomer()->create())
        ->get("/{$locale}/account/bookings/{$booking->public_id}")->assertNotFound();
    $this->post("/{$locale}/cart/{$booking->public_id}/submit")->assertNotFound()
        ->assertSessionMissing('booking_submitted');
})->with(['en', 'ar']);

it('labels missing optional booking values without inventing submitted details', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->create(['customer_id' => $customer->id, 'guest_count' => null, 'event_ends_at' => null]);
    $this->actingAs($customer)->get("/{$locale}/account/bookings/{$booking->public_id}")
        ->assertOk()->assertSee(__('account.not_provided'))->assertSee(__('booking.columns.event_ends_at'))
        ->assertSee(__('booking.columns.guest_count'))->assertSee(__('booking.columns.recipient_phone'));
})->with(['en', 'ar']);

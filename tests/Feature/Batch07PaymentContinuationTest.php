<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Events\BookingSubmittedToVendor;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ConfirmedState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\Event;

it('shows submitted bookings a status path while payment awaits confirmation in English and Arabic', function (string $locale): void {
    Event::fake([BookingSubmittedToVendor::class]);
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->draft()->create(['customer_id' => $customer->id]);
    BookingAddress::factory()->create(['booking_id' => $booking->id]);
    $vendor = BookingVendor::factory()->create(['booking_id' => $booking->id]);
    $item = BookingItem::factory()->rental()->create(['booking_vendor_id' => $vendor->id]);
    Service::query()->findOrFail($item->service_id)->category->occasions()->attach($booking->occasion_id);

    $detailUrl = route('storefront.account.bookings.show', [$locale, $booking->public_id]);
    $listUrl = route('storefront.account.bookings', [$locale]);

    $this->actingAs($customer)
        ->post(route('storefront.cart.submit', [$locale, $booking->public_id]))
        ->assertRedirect($detailUrl)
        ->assertSessionHas('booking_submitted', $booking->public_id);

    $this->get($detailUrl)
        ->assertOk()
        ->assertSee('data-booking-confirmation', false)
        ->assertSee($booking->reference_no)
        ->assertSee(trans('account.payment_waiting_for_confirmation', [], $locale))
        ->assertSee('data-payment-continuation', false)
        ->assertSee('href="'.e($listUrl).'"', false)
        ->assertSee(trans('account.view_booking_status', [], $locale))
        ->assertDontSee(route('payments.initiate', $booking->public_id), false);
})->with(['en', 'ar']);

it('keeps the supported payment action visible only when the confirmed booking is unpaid in English and Arabic', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => ConfirmedState::class,
        'payment_status' => UnpaidState::class,
        'confirmed_at' => now(),
    ]);
    $vendor = BookingVendor::factory()->create(['booking_id' => $booking->id]);
    BookingItem::factory()->rental()->create(['booking_vendor_id' => $vendor->id]);
    $paymentRoute = route('payments.initiate', $booking->public_id);

    $this->actingAs($customer)
        ->get(route('storefront.account.bookings.show', [$locale, $booking->public_id]))
        ->assertOk()
        ->assertSee('action="'.e($paymentRoute).'"', false)
        ->assertSee('data-payment', false)
        ->assertSee(trans('account.pay_booking', [], $locale))
        ->assertDontSee(trans('account.view_booking_status', [], $locale));
})->with(['en', 'ar']);

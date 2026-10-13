<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\AppendCustomerBookingNoteAction;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingCustomerNote;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingDetailPage;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;

function eventNotesFixture(): array
{
    $customer = User::factory()->phoneVerified()->asCustomer()->create();
    $occasion = Occasion::factory()->active()->create();
    $city = City::factory()->create(['is_active' => true]);
    $service = Service::factory()->sale()->published()->create();
    $service->category->occasions()->attach($occasion->id);
    ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 10]);

    return [$customer, $service, $occasion, $city, [
        'service_id' => $service->public_id,
        'occasion' => $occasion->code,
        'event_starts_at' => now()->addDays(5)->toDateTimeString(),
        'guest_count' => 25,
        'notes' => "Instructions <script>alert(1)</script>\n".str_repeat("تعليمات طويلة للمناسبة. Keep this full line.\n", 100).'Final line.',
        'address' => ['city_id' => $city->public_id, 'address_line' => '20 Event Street', 'recipient_name' => 'Recipient', 'recipient_phone_e164' => '+201001234567'],
    ]];
}

it('replaces the celebrant field and stores complete escaped multiline notes for the owning customer', function (string $locale): void {
    [$customer, $service, , , $payload] = eventNotesFixture();
    $this->actingAs($customer)->get("/{$locale}/wizard?service={$service->public_id}")->assertOk()
        ->assertSee('textarea', false)->assertSee('name="notes"', false)->assertDontSee('name="celebrant_name"', false);
    $this->post("/{$locale}/cart/setup", $payload)->assertRedirect("/{$locale}/cart");
    $booking = Booking::query()->where('customer_id', $customer->id)->sole();
    expect($booking->customerNotes()->sole()->body)->toBe($payload['notes'])
        ->and($booking->customerNotes()->sole()->detected_locale)->toBe($locale);
    foreach (["/{$locale}/cart", "/{$locale}/account/bookings/{$booking->public_id}"] as $url) {
        $this->get($url)->assertOk()->assertSee($payload['notes'])->assertDontSee('<script>alert(1)</script>', false);
    }
    $other = User::factory()->asCustomer()->create();
    $this->actingAs($other)->get("/{$locale}/account/bookings/{$booking->public_id}")->assertNotFound();
    expect(fn () => app(AppendCustomerBookingNoteAction::class)->execute($booking, $other->id, 'Foreign note', $locale))
        ->toThrow(ModelNotFoundException::class);
    expect($booking->customerNotes()->count())->toBe(1);
})->with(['en', 'ar']);

it('keeps planner notes in the session without exposing or truncating them in search links', function (string $locale): void {
    [, $service, , $city, $payload] = eventNotesFixture();
    unset($payload['service_id'], $payload['address']);
    $payload['governorate_public_id'] = $city->governorate->public_id;
    $payload['city_public_id'] = $city->public_id;
    $response = $this->post("/{$locale}/wizard/context", $payload)->assertRedirect()
        ->assertSessionHas('storefront.planner_notes', $payload['notes']);
    expect($response->headers->get('Location'))->not->toContain('notes', 'Instructions', 'تعليمات');
    $this->get("/{$locale}/wizard?service={$service->public_id}")->assertRedirect();
    $this->get("/{$locale}/wizard")->assertOk()->assertSee($payload['notes']);
    $otherCity = City::factory()->create();
    $payload['city_public_id'] = $otherCity->public_id;
    $this->post("/{$locale}/wizard/context", $payload)->assertSessionHasErrors('city_public_id');
})->with(['en', 'ar']);

it('returns the same multiline notes on the existing draft API', function (string $locale): void {
    [$customer, , $occasion, , $payload] = eventNotesFixture();
    unset($payload['service_id'], $payload['occasion']);
    $payload['occasion_id'] = $occasion->public_id;
    $this->actingAs($customer, 'sanctum')->postJson('/api/v1/customer/bookings', $payload, ['Accept-Language' => $locale])
        ->assertCreated()->assertJsonPath('data.notes', $payload['notes']);
    expect(BookingCustomerNote::query()->sole()->body)->toBe($payload['notes']);
})->with(['en', 'ar']);

it('rejects oversized notes without truncation or partial booking writes', function (string $locale): void {
    [$customer, , , , $payload] = eventNotesFixture();
    $payload['notes'] = str_repeat('م', 10001);
    $this->actingAs($customer)->post("/{$locale}/cart/setup", $payload)->assertSessionHasErrors('notes');
    expect(Booking::query()->count())->toBe(0)->and(BookingCustomerNote::query()->count())->toBe(0);
    $payload['notes'] = str_repeat('م', 10000);
    $this->post("/{$locale}/cart/setup", $payload)->assertRedirect("/{$locale}/cart");
    expect(BookingCustomerNote::query()->sole()->body)->toBe($payload['notes']);
})->with(['en', 'ar']);

it('appends changed notes to an existing draft but preserves history and legacy celebrant data', function (): void {
    [$customer, , , , $payload] = eventNotesFixture();
    $this->actingAs($customer)->post('/en/cart/setup', $payload)->assertRedirect('/en/cart');
    $booking = Booking::query()->sole();
    $booking->update(['celebrant_name' => 'Historical celebrant']);
    $this->post('/en/cart/setup', $payload)->assertRedirect('/en/cart');
    expect($booking->customerNotes()->count())->toBe(1);
    $payload['notes'] = "Changed instructions\nتعليمات جديدة";
    $this->post('/en/cart/setup', $payload)->assertRedirect('/en/cart');
    expect($booking->customerNotes()->count())->toBe(2)
        ->and($booking->customerNotes()->first()->body)->toBe($payload['notes'])
        ->and($booking->fresh()->celebrant_name)->toBe('Historical celebrant');
});

it('rolls notes back with a failed cart addition', function (): void {
    [$customer, $service, , , $payload] = eventNotesFixture();
    $service->saleDetail->update(['stock_quantity' => 0]);
    $this->actingAs($customer)->post('/en/cart/setup', $payload)->assertSessionHasErrors('service_id');
    expect(Booking::query()->count())->toBe(0)->and(BookingCustomerNote::query()->count())->toBe(0);
});

it('renders event notes only to the owning approved vendor', function (string $locale): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    app()->setLocale($locale);
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    $booking = Booking::factory()->create();
    $allocation = BookingVendor::factory()->create(['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id]);
    $body = "Event instructions\nتعليمات المورد\nFinal line.\n<script>alert('note')</script>";
    app(AppendCustomerBookingNoteAction::class)->execute($booking, $booking->customer_id, $body, $locale);
    $url = VendorBookingDetailPage::getUrl(['bookingVendor' => $allocation->public_id]);
    $other = User::factory()->phoneVerified()->asVendor()->create();
    VendorProfile::factory()->approved()->create(['user_id' => $other->id]);
    $this->actingAs($user, 'vendor')->get($url)->assertOk()->assertSee($body)->assertDontSee("<script>alert('note')</script>", false);
    $this->actingAs($other, 'vendor')->get($url)->assertForbidden();
})->with(['en', 'ar']);

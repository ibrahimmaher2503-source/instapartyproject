<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('rejects changed event context for a rental cart and preserves its inventory hold', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $occasion = Occasion::factory()->create(['is_active' => true]);
    $rental = Service::factory()->rental()->published()->create();
    $newService = Service::factory()->sale()->published()->create();
    $conflictingService = Service::factory()->sale()->published()->create();
    ServiceRentalDetail::factory()->create(['service_id' => $rental->id, 'default_rental_duration_hours' => 4]);
    ServiceSaleDetail::factory()->create(['service_id' => $newService->id, 'stock_quantity' => 5]);
    ServiceSaleDetail::factory()->create(['service_id' => $conflictingService->id, 'stock_quantity' => 5]);
    $rental->category->occasions()->attach($occasion->id);
    $newService->category->occasions()->attach($occasion->id);
    $conflictingService->category->occasions()->attach($occasion->id);
    $oldCity = City::factory()->create(['is_active' => true]);
    $newCity = City::factory()->create(['is_active' => true]);
    $oldStart = now()->addDays(8)->startOfHour();
    $newStart = now()->addDays(12)->startOfHour();

    $this->actingAs($customer)->post('/en/cart/setup', [
        'service_id' => $rental->public_id,
        'occasion' => $occasion->code,
        'event_starts_at' => $oldStart->toDateTimeString(),
        'event_ends_at' => $oldStart->copy()->addHours(2)->toDateTimeString(),
        'guest_count' => 10,
        'celebrant_name' => 'Old name',
        'address' => ['city_id' => $oldCity->public_id, 'address_line' => 'Old address', 'recipient_name' => 'Old recipient', 'recipient_phone_e164' => '+201001234567'],
    ])->assertRedirect('/en/cart');

    $this->post('/en/cart/setup', [
        'service_id' => $newService->public_id,
        'occasion' => $occasion->code,
        'event_starts_at' => $oldStart->toDateTimeString(),
        'event_ends_at' => $oldStart->copy()->addHours(2)->toDateTimeString(),
        'guest_count' => 10,
        'celebrant_name' => 'Old name',
        'address' => ['city_id' => $oldCity->public_id, 'address_line' => 'Old address', 'recipient_name' => 'Old recipient', 'recipient_phone_e164' => '+201001234567'],
    ])->assertRedirect('/en/cart');

    $booking = Booking::query()->where('customer_id', $customer->id)->firstOrFail()->load('address');
    $reservation = DB::table('service_inventory_reservations')->where('service_id', $rental->id)->first();

    $this->from('/en/wizard?service='.$conflictingService->public_id)->post('/en/cart/setup', [
        'service_id' => $conflictingService->public_id,
        'occasion' => $occasion->code,
        'event_starts_at' => $newStart->toDateTimeString(),
        'event_ends_at' => $newStart->copy()->addHours(3)->toDateTimeString(),
        'guest_count' => 20,
        'celebrant_name' => 'New name',
        'notes' => 'New event notes',
        'address' => ['city_id' => $newCity->public_id, 'address_line' => 'New address', 'recipient_name' => 'New recipient', 'recipient_phone_e164' => '+201001234568'],
    ])->assertRedirect('/en/wizard?service='.$conflictingService->public_id)
        ->assertSessionHasErrors(['service_id' => __('storefront.wizard.validation.draft_context_mismatch')]);

    expect(Booking::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and($booking->items()->count())->toBe(2)
        ->and($booking->event_starts_at->toIso8601String())->toBe($oldStart->utc()->toIso8601String())
        ->and($booking->event_ends_at->toIso8601String())->toBe($oldStart->copy()->addHours(2)->utc()->toIso8601String())
        ->and($booking->guest_count)->toBe(10)
        ->and($booking->celebrant_name)->toBe('Old name')
        ->and($booking->address->city_id)->toBe($oldCity->id)
        ->and($booking->address->address_line)->toBe('Old address')
        ->and($booking->address->recipient_name)->toBe('Old recipient')
        ->and($booking->address->recipient_phone_e164)->toBe('+201001234567')
        ->and(BookingAddress::query()->where('booking_id', $booking->id)->count())->toBe(1)
        ->and(BookingItem::query()->count())->toBe(2)
        ->and(DB::table('service_inventory_reservations')->where('service_id', $rental->id)->count())->toBe(1)
        ->and(DB::table('service_inventory_reservations')->where('service_id', $rental->id)->value('reserved_starts_at'))->toBe($reservation->reserved_starts_at)
        ->and(DB::table('service_inventory_reservations')->where('service_id', $rental->id)->value('reserved_ends_at'))->toBe($reservation->reserved_ends_at)
        ->and(DB::table('service_inventory_reservations')->where('service_id', $newService->id)->count())->toBe(1)
        ->and(DB::table('service_inventory_reservations')->where('service_id', $conflictingService->id)->count())->toBe(0);
});

it('updates an empty draft with the new event context before adding its first service', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $occasion = Occasion::factory()->create(['is_active' => true]);
    $service = Service::factory()->sale()->published()->create();
    ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 5]);
    $service->category->occasions()->attach($occasion->id);
    $oldCity = City::factory()->create(['is_active' => true]);
    $newCity = City::factory()->create(['is_active' => true]);
    $oldStart = now()->addDays(8)->startOfHour();
    $newStart = now()->addDays(12)->startOfHour();
    $booking = Booking::factory()->draft()->create([
        'customer_id' => $customer->id,
        'occasion_id' => $occasion->id,
        'event_starts_at' => $oldStart,
        'event_ends_at' => $oldStart->copy()->addHours(2),
        'guest_count' => 5,
    ]);
    BookingAddress::factory()->create([
        'booking_id' => $booking->id,
        'city_id' => $oldCity->id,
        'address_line' => 'Old address',
        'recipient_name' => 'Old recipient',
        'recipient_phone_e164' => '+201001234567',
    ]);

    $this->actingAs($customer)->post('/en/cart/setup', [
        'service_id' => $service->public_id,
        'occasion' => $occasion->code,
        'event_starts_at' => $newStart->toDateTimeString(),
        'event_ends_at' => $newStart->copy()->addHours(3)->toDateTimeString(),
        'guest_count' => 20,
        'celebrant_name' => 'New name',
        'address' => ['city_id' => $newCity->public_id, 'address_line' => 'New address', 'recipient_name' => 'New recipient', 'recipient_phone_e164' => '+201001234568'],
    ])->assertRedirect('/en/cart');

    $booking->refresh()->load('address');
    expect(Booking::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and($booking->items()->count())->toBe(1)
        ->and($booking->event_starts_at->toIso8601String())->toBe($newStart->utc()->toIso8601String())
        ->and($booking->event_ends_at->toIso8601String())->toBe($newStart->copy()->addHours(3)->utc()->toIso8601String())
        ->and($booking->guest_count)->toBe(20)
        ->and($booking->celebrant_name)->toBe('New name')
        ->and($booking->address->city_id)->toBe($newCity->id)
        ->and($booking->address->address_line)->toBe('New address')
        ->and($booking->address->recipient_name)->toBe('New recipient')
        ->and($booking->address->recipient_phone_e164)->toBe('+201001234568')
        ->and(BookingAddress::query()->where('booking_id', $booking->id)->count())->toBe(1);
});

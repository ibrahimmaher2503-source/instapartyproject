<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\CustomerConfirmModifiedBookingAction;
use App\Modules\Booking\Application\DTOs\CustomerModificationDecisionDTO;
use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingModificationItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingDecisionPage;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

function optionalEndFixture(string $type): array
{
    $service = Service::factory()->{$type}()->published()->create();
    if ($type === 'rental') {
        ServiceRentalDetail::factory()->create(['service_id' => $service->id, 'default_rental_duration_hours' => 6]);
    } elseif ($type === 'sale') {
        ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 5]);
    }
    $occasion = Occasion::factory()->active()->create();
    $service->category->occasions()->attach($occasion->id);
    $city = City::factory()->create(['is_active' => true]);

    return [$service, $occasion, $city, [
        'service_id' => $service->public_id,
        'occasion' => $occasion->code,
        'event_starts_at' => now()->addDays(5)->startOfHour()->toDateTimeString(),
        'timezone' => 'Africa/Cairo',
        'address' => [
            'city_id' => $city->public_id,
            'address_line' => '20 Optional End Street',
            'recipient_name' => 'Event Customer',
            'recipient_phone_e164' => '+201001234567',
        ],
    ]];
}

it('saves an unknown event end as null while maintaining type-aware item windows', function (string $locale, string $type, string $mode): void {
    [$service, $occasion, $city, $payload] = optionalEndFixture($type);
    if ($mode !== 'omitted') {
        $payload['event_ends_at'] = $mode === 'null' ? null : '';
    }
    $customer = User::factory()->phoneVerified()->asCustomer()->create();
    $this->actingAs($customer)->post("/{$locale}/cart/setup", $payload)->assertRedirect("/{$locale}/cart");
    $booking = Booking::query()->where('customer_id', $customer->id)->sole();
    $item = $booking->items()->sole();
    $start = Carbon::parse($payload['event_starts_at'], 'Africa/Cairo')->utc();
    expect($booking->event_ends_at)->toBeNull()
        ->and($booking->event_starts_at->equalTo($start))->toBeTrue()
        ->and($item->effective_starts_at->equalTo($start))->toBeTrue();
    if ($type === 'rental') {
        expect($item->effective_ends_at->equalTo($start->copy()->addHours(6)))->toBeTrue();
        $this->assertDatabaseHas('service_inventory_reservations', [
            'booking_item_id' => $item->id,
            'reserved_starts_at' => $start->toDateTimeString(),
            'reserved_ends_at' => $start->copy()->addHours(6)->toDateTimeString(),
        ]);
    } else {
        expect($item->effective_ends_at)->toBeNull();
    }
})->with(['en', 'ar'])->with(['rental', 'sale', 'digital'])->with(['omitted', 'null', 'empty']);

it('creates an API draft with a nullable end and stores offset dates in UTC', function (string $locale, string $mode): void {
    [, $occasion, , $payload] = optionalEndFixture('sale');
    unset($payload['service_id'], $payload['occasion'], $payload['timezone']);
    $payload['occasion_id'] = $occasion->public_id;
    $start = now()->addDays(5)->startOfHour()->setTimezone('Africa/Cairo');
    $payload['event_starts_at'] = $start->toIso8601String();
    if ($mode !== 'omitted') {
        $payload['event_ends_at'] = $mode === 'null' ? null : '';
    }
    $customer = User::factory()->phoneVerified()->asCustomer()->create();
    $this->actingAs($customer, 'sanctum')->postJson('/api/v1/customer/bookings', $payload, ['Accept-Language' => $locale])
        ->assertCreated()->assertJsonPath('data.event_ends_at', null);
    $booking = Booking::query()->where('customer_id', $customer->id)->sole();
    expect($booking->event_ends_at)->toBeNull()
        ->and($booking->event_starts_at->toDateTimeString())->toBe($start->copy()->utc()->toDateTimeString());
})->with(['en', 'ar'])->with(['omitted', 'null', 'empty']);

it('rejects missing starts and malformed or nonpositive ends without creating a draft', function (string $locale, string $invalid): void {
    [, , , $payload] = optionalEndFixture('sale');
    $field = $invalid === 'missing-start' ? 'event_starts_at' : 'event_ends_at';
    if ($invalid === 'missing-start') {
        unset($payload['event_starts_at']);
    } else {
        $payload['event_ends_at'] = match ($invalid) {
            'malformed' => 'not-a-date',
            'before' => Carbon::parse($payload['event_starts_at'])->subHour()->toDateTimeString(),
            'same' => $payload['event_starts_at'],
        };
    }
    $customer = User::factory()->asCustomer()->create();
    $this->actingAs($customer)->post("/{$locale}/cart/setup", $payload)->assertSessionHasErrors($field);
    expect(Booking::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
})->with(['en', 'ar'])->with(['missing-start', 'malformed', 'before', 'same']);

it('honors explicit ends instead of replacing them with the rental default', function (string $locale): void {
    [, , , $payload] = optionalEndFixture('rental');
    $payload['event_ends_at'] = Carbon::parse($payload['event_starts_at'])->addHours(2)->toDateTimeString();
    $customer = User::factory()->asCustomer()->create();
    $this->actingAs($customer)->post("/{$locale}/cart/setup", $payload)->assertRedirect("/{$locale}/cart");
    $booking = Booking::query()->where('customer_id', $customer->id)->sole();
    expect($booking->event_ends_at->diffInHours($booking->event_starts_at, true))->toBe(2.0)
        ->and($booking->items()->sole()->effective_ends_at->equalTo($booking->event_ends_at))->toBeTrue();
})->with(['en', 'ar']);

it('prevents conflicting rentals across the full default window and rolls back their drafts', function (string $locale): void {
    [, , , $payload] = optionalEndFixture('rental');
    $first = User::factory()->asCustomer()->create();
    $this->actingAs($first)->post("/{$locale}/cart/setup", $payload)->assertRedirect("/{$locale}/cart");
    $second = User::factory()->asCustomer()->create();
    $payload['event_starts_at'] = Carbon::parse($payload['event_starts_at'])->addHours(5)->toDateTimeString();
    $this->actingAs($second)->post("/{$locale}/cart/setup", $payload)->assertSessionHasErrors('service_id');
    expect(Booking::query()->where('customer_id', $second->id)->exists())->toBeFalse();
})->with(['en', 'ar']);

it('does not invent a duration for a rental without a valid default', function (string $locale): void {
    [$service, , , $payload] = optionalEndFixture('rental');
    $service->rentalDetail->delete();
    $customer = User::factory()->asCustomer()->create();
    $this->actingAs($customer)->post("/{$locale}/cart/setup", $payload)->assertSessionHasErrors('service_id');
    expect(session('errors')->first('service_id'))->toBe(__('storefront.wizard.validation.rental_duration_required'))
        ->and(Booking::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
})->with(['en', 'ar']);

it('preserves nullable event ends when accepting rental additions and checks their default window', function (bool $conflicting): void {
    Event::fake();
    [$service] = optionalEndFixture('rental');
    $customer = User::factory()->phoneVerified()->asCustomer()->create();
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'event_ends_at' => null,
        'lifecycle_status' => CustomerReviewState::class,
    ]);
    $allocation = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $service->vendor_profile_id,
        'sub_status' => VendorSubStatus::Modified,
    ]);
    $modification = BookingModification::factory()->create([
        'booking_vendor_id' => $allocation->id,
        'proposed_by' => $service->vendor->user_id,
        'status' => ModificationStatus::Pending,
        'expires_at' => now()->addHour(),
    ]);
    BookingModificationItem::create([
        'booking_modification_id' => $modification->id,
        'change_kind' => ModificationChangeKind::Add,
        'payload' => ['service_id' => $service->id, 'product_type' => 'rental', 'quantity' => 1],
    ]);
    if ($conflicting) {
        ServiceInventoryReservation::factory()->create([
            'service_id' => $service->id,
            'product_type' => 'rental',
            'status' => 'confirmed',
            'reserved_starts_at' => $booking->event_starts_at->copy()->addHours(5),
            'reserved_ends_at' => $booking->event_starts_at->copy()->addHours(8),
        ]);
    }
    try {
        app(CustomerConfirmModifiedBookingAction::class)->execute(new CustomerModificationDecisionDTO(
            $booking->id, $customer->id, $modification->public_id, 'accepted', (string) Str::uuid(),
        ));
        expect($conflicting)->toBeFalse();
    } catch (HttpException $exception) {
        expect($conflicting)->toBeTrue()->and($exception->getStatusCode())->toBe(409);
        expect($modification->fresh()->status)->toBe(ModificationStatus::Pending);
    }
    expect($booking->fresh()->event_ends_at)->toBeNull();
    if ($conflicting) {
        expect($allocation->items()->count())->toBe(0);
    } else {
        $item = $allocation->items()->sole();
        expect($item->effective_starts_at->diffInHours($item->effective_ends_at))->toBe(6.0);
        $this->assertDatabaseHas('service_inventory_reservations', [
            'booking_item_id' => $item->id,
            'reserved_ends_at' => $item->effective_ends_at->toDateTimeString(),
        ]);
    }
})->with([false, true]);

it('shows a late-window inventory conflict to the owning vendor when the event end is unknown', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    $booking = Booking::factory()->create(['event_ends_at' => null]);
    $allocation = BookingVendor::factory()->create(['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id]);
    $service = Service::factory()->rental()->published()->create(['vendor_profile_id' => $vendor->id]);
    BookingItem::factory()->create([
        'booking_vendor_id' => $allocation->id,
        'service_id' => $service->id,
        'product_type' => 'rental',
        'effective_starts_at' => $booking->event_starts_at,
        'effective_ends_at' => $booking->event_starts_at->copy()->addHours(6),
    ]);
    ServiceInventoryReservation::factory()->create([
        'service_id' => $service->id,
        'product_type' => 'rental',
        'status' => 'confirmed',
        'reserved_starts_at' => $booking->event_starts_at->copy()->addHours(5),
        'reserved_ends_at' => $booking->event_starts_at->copy()->addHours(8),
    ]);
    $this->actingAs($user, 'vendor')->get(VendorBookingDecisionPage::getUrl(['bookingVendor' => $allocation->public_id]))
        ->assertOk()->assertSeeText(__('vendor-portal.decision.inventory.warning'));
});

it('accepts start-only planner searches and still rejects earlier supplied ends', function (string $locale): void {
    [, $occasion, $city, $payload] = optionalEndFixture('sale');
    $filters = [
        'planner' => 1,
        'occasion' => $occasion->code,
        'governorate_public_id' => $city->governorate->public_id,
        'city_public_id' => $city->public_id,
        'event_starts_at' => $payload['event_starts_at'],
    ];
    $this->get("/{$locale}/search?".http_build_query($filters))->assertOk();
    $filters['event_ends_at'] = Carbon::parse($payload['event_starts_at'])->subHour()->toDateTimeString();
    $this->get("/{$locale}/search?".http_build_query($filters))->assertSessionHasErrors('event_ends_at');
})->with(['en', 'ar']);

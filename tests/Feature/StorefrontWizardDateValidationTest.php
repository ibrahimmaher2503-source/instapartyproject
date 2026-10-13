<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceAvailabilityBlock;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

it('keeps planner dates and occasion visible after localized timezone validation errors', function (string $locale, string $invalidField): void {
    $timezone = 'America/Los_Angeles';
    $occasion = Occasion::factory()->active()->create();
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    $startsAt = CarbonImmutable::now($timezone)->addDays(3)->setTime(12, 0)->format('Y-m-d\TH:i');
    $endsAt = CarbonImmutable::parse($startsAt, $timezone)->addHours(2)->format('Y-m-d\TH:i');

    if ($invalidField === 'event_starts_at') {
        $startsAt = CarbonImmutable::now($timezone)->subHours(2)->format('Y-m-d\TH:i');
    } else {
        $endsAt = CarbonImmutable::parse($startsAt, $timezone)->subMinute()->format('Y-m-d\TH:i');
    }

    $wizardUrl = "/{$locale}/wizard";
    $payload = [
        'occasion' => $occasion->code,
        'governorate_public_id' => $governorate->public_id,
        'city_public_id' => $city->public_id,
        'event_starts_at' => $startsAt,
        'event_ends_at' => $endsAt,
        'guest_count' => 42,
        'notes' => 'Keep the rest of the event details.',
        'timezone' => $timezone,
    ];

    $this->from($wizardUrl)->post("/{$locale}/wizard/context", $payload)
        ->assertRedirect($wizardUrl)
        ->assertSessionHasErrors($invalidField)
        ->assertSessionHasInput('occasion', $occasion->code)
        ->assertSessionHasInput('event_starts_at', $startsAt)
        ->assertSessionHasInput('guest_count', 42)
        ->assertSessionHasInput('notes', $payload['notes'])
        ->assertSessionHasInput('timezone', $timezone);

    $this->get($wizardUrl)->assertOk()
        ->assertSee('data-initial-step="0"', false)
        ->assertSee('data-initial-governorate="'.$governorate->public_id.'"', false)
        ->assertSee('data-initial-city="'.$city->public_id.'"', false)
        ->assertSee('data-initial-date="'.$startsAt.'"', false)
        ->assertSee('name="timezone" value="'.$timezone.'"', false)
        ->assertSee('aria-checked="true"', false)
        ->assertSee(__('storefront.wizard.validation.times_invalid'))
        ->assertSee('value="42"', false)
        ->assertSee($payload['notes']);
})->with(['en', 'ar'])->with(['event_starts_at', 'event_ends_at']);

it('restores booking wizard UTC old input in the submitted timezone', function (): void {
    $timezone = 'America/Los_Angeles';
    $customer = User::factory()->asCustomer()->create();
    $service = Service::factory()->sale()->published()->create();
    $occasion = Occasion::factory()->active()->create();
    $city = City::factory()->create(['is_active' => true]);
    $startsAt = CarbonImmutable::now($timezone)->subHours(2)->format('Y-m-d\\TH:i');
    $utcStartsAt = CarbonImmutable::parse($startsAt, $timezone)->utc()->toIso8601String();
    $wizardUrl = '/en/wizard?service='.$service->public_id;
    $payload = [
        'service_id' => $service->public_id,
        'occasion' => $occasion->code,
        'governorate_public_id' => $city->governorate->public_id,
        'city_public_id' => $city->public_id,
        'event_starts_at' => $startsAt,
        'timezone' => $timezone,
        'address' => [
            'city_id' => $city->public_id,
            'address_line' => '20 Booking Date Street',
            'recipient_name' => 'Booking Date Customer',
            'recipient_phone_e164' => '+201001234567',
        ],
    ];

    $this->actingAs($customer)->from($wizardUrl)->post('/en/cart/setup', $payload)
        ->assertRedirect($wizardUrl)
        ->assertSessionHasErrors('event_starts_at')
        ->assertSessionHasInput('event_starts_at', $utcStartsAt)
        ->assertSessionHasInput('occasion', $occasion->code)
        ->assertSessionHasInput('timezone', $timezone);

    $this->get($wizardUrl)->assertOk()
        ->assertSee('data-initial-step="0"', false)
        ->assertSee('data-initial-governorate="'.$city->governorate->public_id.'"', false)
        ->assertSee('data-initial-city="'.$city->public_id.'"', false)
        ->assertSee('data-initial-date="'.$utcStartsAt.'"', false)
        ->assertSee('name="timezone" value="'.$timezone.'"', false)
        ->assertSee('aria-checked="true"', false);
});

it('redirects planner dates as UTC instants while retaining the viewer local date', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 01:00:00', 'UTC'));
    $timezone = 'America/Los_Angeles';
    $occasion = Occasion::factory()->active()->create();
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    $category = Category::factory()->active()->create(['allowed_product_types' => [ProductType::Rental->value]]);
    $category->occasions()->attach($occasion->id, ['sort_order' => 1]);
    $vendor = VendorProfile::factory()->approved()->create([
        'primary_governorate_id' => $governorate->id,
        'primary_city_id' => $city->id,
    ]);
    $service = Service::factory()->rental()->published()->create([
        'vendor_profile_id' => $vendor->id,
        'category_id' => $category->id,
        'name' => ['en' => 'Timezone Availability Regression Rental', 'ar' => 'خدمة اختبار توفر المنطقة الزمنية'],
    ]);
    $startsAt = CarbonImmutable::now($timezone)->setTime(20, 30);
    $endsAt = $startsAt->addHours(2);
    ServiceAvailabilityBlock::query()->create([
        'public_id' => (string) Str::ulid(),
        'service_id' => $service->id,
        'starts_at' => $startsAt->utc()->subMinutes(30),
        'ends_at' => $startsAt->utc()->addMinutes(30),
        'reason' => ['en' => 'Reserved', 'ar' => 'محجوز'],
    ]);

    $response = $this->post('/en/wizard/context', [
        'occasion' => $occasion->code,
        'governorate_public_id' => $governorate->public_id,
        'city_public_id' => $city->public_id,
        'event_starts_at' => $startsAt->format('Y-m-d\\TH:i'),
        'event_ends_at' => $endsAt->format('Y-m-d\\TH:i'),
        'timezone' => $timezone,
    ])->assertRedirect();

    $searchUrl = $response->headers->get('Location');
    parse_str((string) parse_url((string) $searchUrl, PHP_URL_QUERY), $query);

    expect($query['event_date'])->toBe($startsAt->toDateString())
        ->and($query['event_starts_at'])->toBe($startsAt->utc()->toIso8601String())
        ->and($query['event_ends_at'])->toBe($endsAt->utc()->toIso8601String());

    $this->get((string) $searchUrl)->assertOk()
        ->assertSee('name="event_date" value="'.$startsAt->toDateString().'"', false)
        ->assertSee('name="event_date" value="'.$startsAt->toDateString().'" type="date" lang="en" min="'.$startsAt->toDateString().'"', false)
        ->assertSee('name="event_starts_at" value="'.$query['event_starts_at'].'"', false)
        ->assertSee('name="event_ends_at" value="'.$query['event_ends_at'].'"', false)
        ->assertDontSee('Timezone Availability Regression Rental');

    $this->get(route('storefront.search', [...$query, 'price_min' => 100]))->assertOk()
        ->assertSee('name="event_date" value="'.$startsAt->toDateString().'" type="date" lang="en" min="'.$startsAt->toDateString().'"', false)
        ->assertSee('name="event_starts_at" value="'.$query['event_starts_at'].'"', false)
        ->assertSee('name="event_ends_at" value="'.$query['event_ends_at'].'"', false)
        ->assertDontSee('Timezone Availability Regression Rental');
});

it('rejects an invalid wizard timezone as a field error', function (): void {
    $occasion = Occasion::factory()->active()->create();
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    $startsAt = CarbonImmutable::now('UTC')->addDays(3)->setTime(12, 0)->format('Y-m-d\\TH:i');

    $this->from('/en/wizard')->post('/en/wizard/context', [
        'occasion' => $occasion->code,
        'governorate_public_id' => $governorate->public_id,
        'city_public_id' => $city->public_id,
        'event_starts_at' => $startsAt,
        'timezone' => 'Not/A_Real_Timezone',
    ])->assertRedirect('/en/wizard')
        ->assertSessionHasErrors('timezone')
        ->assertSessionHasInput('occasion', $occasion->code)
        ->assertSessionHasInput('event_starts_at', $startsAt);

    $this->get('/en/wizard')->assertOk()
        ->assertSee('data-initial-step="0"', false)
        ->assertSee('data-initial-date="'.$startsAt.'"', false)
        ->assertSee('aria-checked="true"', false);
});

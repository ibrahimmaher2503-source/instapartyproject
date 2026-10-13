<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('explains invalid recipient phones and preserves the wizard fields without creating a booking', function (string $locale, string $phone): void {
    $customer = User::factory()->asCustomer()->create();
    $service = Service::factory()->sale()->published()->create();
    $occasion = Occasion::factory()->active()->create();
    $city = City::factory()->create(['is_active' => true]);
    $wizardUrl = "/{$locale}/wizard?service={$service->public_id}";
    $payload = [
        'service_id' => $service->public_id,
        'occasion' => $occasion->code,
        'event_starts_at' => now()->addDays(2)->toDateTimeString(),
        'guest_count' => 25,
        'notes' => 'Keep the event instructions.',
        'governorate_public_id' => $city->governorate->public_id,
        'city_public_id' => $city->public_id,
        'address' => [
            'city_id' => $city->public_id,
            'address_line' => '20 Phone Test Street',
            'recipient_name' => 'Phone Test Customer',
            'recipient_phone_e164' => $phone,
        ],
    ];

    $this->actingAs($customer)->from($wizardUrl)->post("/{$locale}/cart/setup", $payload)
        ->assertRedirect($wizardUrl)
        ->assertSessionHasErrors('address.recipient_phone_e164')
        ->assertSessionHasInput('address.recipient_phone_e164', $phone)
        ->assertSessionHasInput('notes', $payload['notes']);

    $this->get($wizardUrl)->assertOk()
        ->assertSee(__('storefront.wizard.step3.recipient_phone_hint'))
        ->assertSee(__('storefront.wizard.validation.recipient_phone_invalid'))
        ->assertSee('id="planner-recipient-phone-error"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('data-initial-step="2"', false)
        ->assertSee('value="'.$phone.'"', false)
        ->assertSee($payload['address']['address_line'])
        ->assertSee($payload['notes']);
    expect(Booking::query()->count())->toBe(0);

    ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 5]);
    $payload['address']['recipient_phone_e164'] = '+201000000000';
    $this->post("/{$locale}/cart/setup", $payload)->assertRedirect("/{$locale}/cart");
    expect(Booking::query()->sole()->address->recipient_phone_e164)->toBe('+201000000000');
})->with(['en', 'ar'])->with(['01000000000', 'abc']);

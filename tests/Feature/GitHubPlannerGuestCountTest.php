<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;

it('documents the guest bound and enforces it on the wizard and direct search', function (string $locale): void {
    $occasion = Occasion::factory()->active()->create();
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    $context = [
        'occasion' => $occasion->code,
        'governorate_public_id' => $governorate->public_id,
        'city_public_id' => $city->public_id,
        'event_starts_at' => now()->addMonths(5)->format('Y-m-d').'T12:00',
        'guest_count' => 10000,
    ];
    $this->get("/{$locale}/wizard")->assertOk()
        ->assertSee('max="10000"', false)
        ->assertSee(__('storefront.wizard.step3.guests_max_hint'));

    foreach ([0, 10001, 99999999999, '1.5'] as $count) {
        $this->post("/{$locale}/wizard/context", [...$context, 'guest_count' => $count])
            ->assertRedirect()->assertSessionHasErrors('guest_count');
        $this->get("/{$locale}/search?guest_count=".$count)
            ->assertRedirect()->assertSessionHasErrors('search');
    }

    $this->flushSession();
    $this->post("/{$locale}/wizard/context", $context)
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->get("/{$locale}/search?guest_count=10000")->assertOk();
})->with(['en', 'ar']);
